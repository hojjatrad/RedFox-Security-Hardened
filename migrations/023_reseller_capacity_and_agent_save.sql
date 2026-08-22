-- Normalize reseller panel capacity values; invalid legacy values must never block all sales.
UPDATE marzban_panel SET limit_panel='unlimited'
WHERE limit_panel IS NULL OR TRIM(limit_panel)='' OR LOWER(TRIM(limit_panel)) IN ('unlimted','unlimited','none','null','0','-1') OR TRIM(limit_panel)='نامحدود';
