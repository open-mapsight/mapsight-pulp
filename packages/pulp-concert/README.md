# Pulp Concert

Extension for the Pulp library to parse and process Concert XML data.

## Features

- **Multi-domain Data Support:**
  - **Traffic Data:** Parse flow, speed, and other traffic-related metrics.
  - **Parking Data:** Extract parking occupancy, availability, and status.
  - **Traffic Light Status:** Monitor the status of traffic signals.
- **Data Merging:** Merge parsed data into GeoJSON or KML formats.
- **Coordinate Projection:** Handle coordinate system transformations for the parsed data.
- **Flexible Handlers:** Abstract classes provided for custom merging and projection logic.
