-- US ZIP for NOAA weather alerts (dashboard card)
ALTER TABLE tenants
ADD COLUMN zip_code VARCHAR(10) NULL AFTER timezone;
