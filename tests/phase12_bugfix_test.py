#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1];e=[]
def need(p,x):
 s=(R/p).read_text(errors='ignore')
 if x not in s:e.append(f'{p}: missing {x}')
need('migrations/011_channels_panel_provisioning.sql','ADD COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY')
need('panel/channels.php','rx_normalize_channel')
need('panel/channels.php','lastInsertId')
need('panel/channels.php','Migration 011')
need('panel/panels.php',"$requestedType === 'pasargard' ? 'marzban'")
need('panel/panels.php','version_panel,inbounds,proxies,provisioning_status')
need('panel/panels.php','redfox_sync_panel_reference')
need('panel/panels.php','reference_username')
need('panels.php','Marzban/Passargard inbound and proxy settings are not synchronized')
need('migrations/011_channels_panel_provisioning.sql',"type='marzban' WHERE type='pasargard'")
if e:print('\n'.join('FAIL '+x for x in e));sys.exit(1)
print('OK: channel and web panel provisioning bugfix assertions passed')
