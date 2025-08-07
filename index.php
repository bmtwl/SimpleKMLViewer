<?php
// Include the conversion script
include 'convert.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>KML Network Viewer</title>

  <!-- Leaflet -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <!-- Leaflet Tree Control -->
  <link rel="stylesheet" href="lib/leaflet-control-layers-tree/L.Control.Layers.Tree.css" />
  <script src="lib/leaflet-control-layers-tree/L.Control.Layers.Tree.js"></script>

  <!-- Custom CSS -->
  <link rel="stylesheet" href="viewer.css" />
</head>
<body>

  <!-- Map -->
  <div id="map"></div>

  <!-- Custom JS -->
  <script src="viewer.js"></script>

</body>
</html>

