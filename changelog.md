# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2020-01-09
### Added
* Changed gs_id_code to receiver_gs_id_code to be more precise and allow future customization such as sender_gs_id_code

## [1.0.1] - 2020-01-09
### Added
* Added gs_id_code property to \Elevation\EDIFile\Group for specifying a custom GS03 segment value. This can be utilized in situations where GS03 should be different than the receiver_id_code.