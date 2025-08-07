# Simple KML Viewer

A web-based visualization tool for displaying pin and path data stored in KML format. This application converts KML files to GeoJSON and renders them on an interactive map using Leaflet.js.

## Features

- **KML to GeoJSON Conversion**: Automatically converts KML files to GeoJSON format for web compatibility
- **Interactive Map Visualization**: Displays map elements including nodes (pins) and connections (paths)
- **Layer Management**: Organizes features by folders with a tree-based layer control
- **Rich Popups**: Shows detailed information including descriptions, images, and metadata
- **Multiple Base Maps**: Switch between satellite and lineart (OpenStreetMap) base layers
- **Hover Tooltips**: Displays feature names when hovering over paths
- **Automatic Caching**: Efficiently caches converted GeoJSON based on file modification times
- **Responsive Design**: Works on various screen sizes and devices

## Prerequisites

- Web server with PHP support (PHP 5.6 or higher)
- Modern web browser with JavaScript enabled

## Installation

1. Clone or download this repository to your web server's document root:
   ```bash
   git clone https://github.com/bmtwl/SimpleKMLViewer /var/www/
   ```

2. Ensure the web server has read/write permissions for the directory:
   ```bash
   chmod -R 755 /var/www/nettools/topologymap
   chown -R www-data:www-data /var/www/nettools/topologymap  # Adjust user/group as needed
   ```

3. Place your KML file in the project directory named `kml/source.kml`

## Usage

1. Access the application through your web browser:
   ```
   http://your-server/SimpleKMLViewer/index.php
   ```

2. The application will automatically:
   - Convert your KML file to GeoJSON if needed
   - Display the pins and paths on an interactive map
   - Organize features in the layer control by folder

3. Interact with the map:
   - Click on pins or paths to view detailed information
   - Hover over paths to see their names
   - Use the layer control to show/hide different elements
   - Switch between satellite and lineart base maps
   - Zoom and pan using standard map controls

## KML Structure Guidelines

For optimal visualization, structure your KML file as follows:

### Folders
Organize your elements into meaningful folders:
- `Nodes`: For nodes/points
- Custom folders for other elements (extensible via source code edits)

### Features
Each placemark should include:
- **Name**: A descriptive title for the feature
- **Description**: Detailed information (supports HTML)
- **ExtendedData**: Key-value pairs for metadata
- **Style**: Visual styling (color, width) for paths

### Example KML Structure
```xml
<kml>
  <Document>
    <Folder>
      <name>Nodes</name>
      <Placemark>
        <name>Router A</name>
        <Point>
          <coordinates>longitude,latitude</coordinates>
        </Point>
      </Placemark>
    </Folder>
    <Folder>
      <name>Fiber Links</name>
      <Placemark>
        <name>Main Backbone</name>
        <LineString>
          <coordinates>
            longitude1,latitude1
            longitude2,latitude2
          </coordinates>
        </LineString>
      </Placemark>
    </Folder>
  </Document>
</kml>
```

## Technical Architecture

### Backend (PHP)
- **convert.php**: Handles KML to GeoJSON conversion
  - Parses KML using SimpleXML
  - Processes styles and geometry
  - Caches converted data for performance
  - Extracts feature properties and metadata

### Frontend (JavaScript/CSS)
- **viewer.js**: Manages map rendering and interaction
  - Uses Leaflet.js for map visualization
  - Implements layer management
  - Handles popup and tooltip display
  - Manages base map switching

- **viewer.css**: Provides styling for UI elements
  - Map container and controls
  - Popup and tooltip styling
  - Layer tree appearance

## Customization

### Styling
Modify `viewer.css` to change:
- Map element appearances
- Popup and tooltip styles
- Layer control layout

### Map Behavior
Adjust settings in `viewer.js` to modify:
- Initial map view and zoom level
- Base map options
- Feature styling rules
- Popup content formatting

### KML Processing
Modify `convert.php` to:
- Add support for additional KML elements
- Change how properties are extracted
- Modify caching behavior

## Troubleshooting

### Map Not Loading
1. Verify `kml/source.kml` exists and is readable
2. Check web server error logs for PHP issues
3. Ensure the `geojson` directory is writable

### Features Not Displaying Correctly
1. Check browser console for JavaScript errors
2. Verify KML structure follows guidelines
3. Confirm feature coordinates are valid

### Performance Issues
1. For large KML files, consider simplifying geometries
2. Check web server resources and PHP memory limits

## File Structure

```
/var/www/nettools/topologymap/
├── convert.php              # KML to GeoJSON converter
├── index.php                # Main application page
├── viewer.js                # Map visualization logic
├── viewer.css               # Styling for map elements
├── kml/
│   └── source.kml          # Input KML file (required)
├── geojson/
│   └── source.geojson      # Generated GeoJSON (auto-created)
└── lib/
    └── leaflet-control-layers-tree/  # Layer tree control library
```

## Dependencies

- [Leaflet.js](https://leafletjs.com/) - Interactive map library
- [Leaflet Control Layers Tree](https://github.com/jjimenezshaw/Leaflet.Control.Layers.Tree) - Tree-based layer control
- [Esri World Imagery](https://www.esri.com/en-us/arcgis/products/arcgis-platform/services/basemap-layer-service) - Satellite base map tiles
- [OpenStreetMap](https://www.openstreetmap.org/) - Lineart base map tiles

## Support

For issues, questions, or contributions, please:
1. Check existing issues in the repository
2. Create a new issue with detailed information
3. Submit pull requests for improvements
```
