// Initialize map
const map = L.map('map').setView([0, 0], 2);

// Add base tiles (satellite by default)
const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
  attribution: 'Tiles &copy; Esri'
}).addTo(map);

const lineart = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors'
});

const baseMaps = {
  "Satellite": satellite,
  "Lineart": lineart
};

// Feature layers storage
let pathLayers = {};
let pinLayer = null;
let pinLabels = [];

// Add base layers control
L.control.layers(baseMaps, null, { position: 'topright' }).addTo(map);

// Load GeoJSON with error handling
fetch('geojson/source.geojson')
  .then(response => {
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    return response.json();
  })
  .then(geojsonData => {
    console.log('GeoJSON data loaded successfully', geojsonData);
    initMapLayers(geojsonData);
  })
  .catch(error => {
    console.error('Error loading GeoJSON data:', error);
    // Create a temporary div to show error message on map
    const errorDiv = L.DomUtil.create('div', 'error-message');
    errorDiv.innerHTML = '<div style="background:white;padding:10px;border-radius:5px;">Error loading map data. Please check the console for details.</div>';
    const errorControl = L.control({ position: 'topright' });
    errorControl.onAdd = function() { return errorDiv; };
    errorControl.addTo(map);
  });

/**
 * Initialize map layers from GeoJSON data
 * @param {Object} geojsonData - GeoJSON feature collection
 */
function initMapLayers(geojsonData) {
  try {
    // Separate pins and paths
    const pins = [];
    const paths = [];

    geojsonData.features.forEach(feature => {
      if (feature.geometry.type === 'Point') {
        pins.push(feature);
      } else {
        paths.push(feature);
      }
    });

    // Create pin layer (always visible)
    pinLayer = L.geoJSON(pins, {
      pointToLayer: (feature, latlng) => {
        return L.circleMarker(latlng, getPinStyle(feature));
      },
      onEachFeature: (feature, layer) => {
        const props = feature.properties;
        const popupContent = createPopupContent(props);
        layer.bindPopup(popupContent);

        // Add label to pin
        const name = props.name || '';
        if (name) {
          const label = L.marker(layer.getLatLng(), {
            icon: L.divIcon({
              className: 'pin-label',
              html: `<div class="pin-label-text">${name}</div>`,
              iconSize: [100, 20],
              iconAnchor: [0, 0] // Position label at the marker position
            })
          });
          pinLabels.push(label);
        }
      }
    }).addTo(map);

    // Add all pin labels
    pinLabels.forEach(label => label.addTo(map));

    // Create individual layers for paths only
    paths.forEach((feature, index) => {
      const layer = createPathLayer(feature);
      pathLayers[`path_${index}`] = {
        layer: layer,
        feature: feature,
        visible: true
      };
      // Add all path layers to map by default
      layer.addTo(map);
    });

    // Build layer tree with paths only
    const layerTree = buildPathLayerTree(paths);

    // Add tree control with checkboxes for paths only
    const treeControl = L.control.layers.tree(null, layerTree, {
      collapsed: false,
      namedToggle: true,
      position: 'topleft'
    }).addTo(map);

    // Fit to bounds
    const allLayers = L.featureGroup([...Object.values(pathLayers).map(f => f.layer), pinLayer]);
    const bounds = allLayers.getBounds();

    if (bounds.isValid()) {
      map.fitBounds(bounds, { padding: [50, 50] });
    } else {
      console.warn("No valid bounds found. Showing default view.");
      map.setView([0, 0], 2);
    }

    console.log('Map layers initialized successfully');

  } catch (error) {
    console.error('Error initializing map layers:', error);
  }
}

/**
 * Create a layer for a single path feature
 * @param {Object} feature - GeoJSON feature
 * @returns {L.GeoJSON} Leaflet GeoJSON layer
 */
function createPathLayer(feature) {
  return L.geoJSON(feature, {
    style: getPathStyle,
    onEachFeature: (feature, layer) => {
      const props = feature.properties;
      const popupContent = createPopupContent(props);
      layer.bindPopup(popupContent);

      // Add hover tooltip with title
      const name = props.name || 'Unnamed Path';
      layer.bindTooltip(name, {
        permanent: false,
        direction: 'auto',
        className: 'path-tooltip'
      });
    }
  });
}

/**
 * Create formatted popup content from feature properties
 * @param {Object} props - Feature properties
 * @returns {string} Formatted HTML popup content
 */
function createPopupContent(props) {
  // Format feature name
  const name = props.name || 'Unnamed';

  // Format description (remove images since they're handled separately)
  let description = props.description || '';
  description = description.replace(/<img[^>]*>/gi, ''); // Remove image tags from description
  description = description.trim();

  // Format images
  let imagesHtml = '';
  if (props.images && props.images.length > 0) {
    imagesHtml = props.images
      .map(img => `<img src="${img}" style="max-width:100%; height:auto; margin:5px 0;" alt="Feature image">`)
      .join('');
  }

  // Format metadata
  let metaHtml = '';
  if (props.meta && Object.keys(props.meta).length > 0) {
    metaHtml = '<div class="mt-2"><strong>Metadata:</strong><ul class="list-disc pl-5">';
    for (const [key, value] of Object.entries(props.meta)) {
      metaHtml += `<li><strong>${key}:</strong> ${value}</li>`;
    }
    metaHtml += '</ul></div>';
  }

  // Format folder info
  const folderHtml = props.folder ? `<div class="text-sm text-gray-600">Folder: ${props.folder}</div>` : '';

  return `
    <div class="popup-content">
      <h3 class="font-bold text-lg mb-1">${name}</h3>
      ${folderHtml}
      ${description ? `<div class="my-2">${description}</div>` : ''}
      ${imagesHtml ? `<div class="my-2">${imagesHtml}</div>` : ''}
      ${metaHtml}
    </div>
  `;
}

/**
 * Get style for pin features
 * @param {Object} feature - GeoJSON feature
 * @returns {Object} Leaflet style object
 */
function getPinStyle(feature) {
  const folder = feature.properties.folder;
  switch (folder) {
    case "Nodes": return { color: "green", radius: 6 };
    default: return { color: "gray", radius: 5 };
  }
}

/**
 * Get style for path features
 * @param {Object} feature - GeoJSON feature
 * @returns {Object} Leaflet style object
 */
function getPathStyle(feature) {
  const style = feature.properties.style;
  if (style) {
    return {
      color: style.color || 'blue',
      weight: style.width || 3
    };
  }

  // Fallback
  const folder = feature.properties.folder;
  switch (folder) {
    case "Fiber Links": return { color: "blue", weight: 3 };
    case "Copper Links": return { color: "orange", weight: 2 };
    default: return { color: "gray", weight: 2 };
  }
}

/**
 * Build layer tree with paths only
 * @param {Array} paths - Array of path features
 * @returns {Object} Layer tree structure
 */
function buildPathLayerTree(paths) {
  // Group paths by folder
  const grouped = {};
  paths.forEach((feature, index) => {
    const folder = feature.properties.folder || 'Uncategorized';
    if (!grouped[folder]) grouped[folder] = [];
    grouped[folder].push({feature, index});
  });

  // Create tree structure
  const folderNodes = Object.keys(grouped).map(folder => {
    const pathNodes = grouped[folder].map(({feature, index}) => {
      const name = feature.properties.name || `Path ${index}`;
      return {
        label: name,
        layer: pathLayers[`path_${index}`].layer,
        collapsed: false
      };
    });

    return {
      label: folder,
      children: pathNodes,
      collapsed: false
    };
  });

  return {
    label: "Network Paths",
    children: folderNodes,
    collapsed: false
  };
}

