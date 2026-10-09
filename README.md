# JalRakshak AWS

Flood early-warning and response platform. Built for Environmental Hacks
(WeMakeDevs x AWS, Bharat Builds Tour). Track: Heat and Water (floods).

Built fresh for this hackathon. Full README lands at the end of the build.

- `api/` Laravel 12 REST API (PHP 8.3+, MySQL)
- `web/` React + Vite frontend (citizen page + officer dashboard, Leaflet map)
- `deploy/` EC2 deployment notes and Nginx config

## AWS services

- **Amazon EC2** hosts the whole app (Nginx + PHP-FPM + MySQL on one instance)
- **Amazon S3** stores per-zone inundation maps
- **Amazon SNS** fans out flood alerts to subscribed citizens
