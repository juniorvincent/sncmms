-- ============================================================
-- Migration: Phase 2 — Knowledge Base
-- ============================================================
USE sncmms;

CREATE TABLE IF NOT EXISTS kb_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'other',
    content TEXT NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Seed starter articles (created_by left NULL — no admin account is
-- guaranteed to exist yet at import time, per the project's existing
-- "no hardcoded admin seed" decision — see setup/create_admin.php).
INSERT INTO kb_articles (title, category, content) VALUES
('How to reset a router', 'network', 'Unplug the router from power. Wait 10 seconds. Locate the small reset button (usually recessed) on the back of the unit. Press and hold it for 10 seconds using a pin while plugging the power back in. Wait 2-3 minutes for the router to fully restart. Note: this restores factory settings, so any custom Wi-Fi name/password will need to be reconfigured.'),
('How to configure a switch', 'network', 'Connect to the switch via its management IP using a browser, or via console cable for unmanaged setup. Log in with the default credentials (check the device label). Assign VLANs if needed. Set port speed/duplex to Auto unless a device requires a fixed setting. Save the configuration and, if supported, back up the config file before making further changes.'),
('How to troubleshoot Wi-Fi', 'network', '1) Confirm the device''s Wi-Fi is turned on. 2) Check signal strength — move closer to the access point. 3) Restart the affected device. 4) Restart the router/access point. 5) Check if other devices have the same issue (isolates device vs network problem). 6) Verify the correct Wi-Fi password is being used. 7) If only one device is affected, forget the network on that device and reconnect.'),
('How to solve DHCP problems', 'network', 'If a device isn''t getting an IP address: 1) Restart the device''s network adapter. 2) Check the DHCP server (router) is running and has free addresses in its pool. 3) Release/renew the IP (ipconfig /release then /renew on Windows). 4) Check for IP address conflicts. 5) Confirm the DHCP scope hasn''t been exhausted — expand the pool range if needed.'),
('How to check network connectivity', 'network', 'Use ping to test connectivity: ping 8.8.8.8 tests general internet access; ping the default gateway tests local network access. Use tracert (Windows) or traceroute (Linux/Mac) to see where a connection fails along the path. Check physical cabling and link lights on network ports as a first step.'),
('Common printer problems', 'printer', 'Printer not responding: check it''s powered on and connected (USB/network). Paper jams: open all access panels and remove torn paper fully. Poor print quality: run the built-in cleaning cycle and check ink/toner levels. Printer shows offline: restart the print spooler service, or remove and re-add the printer in the OS settings.'),
('Basic network troubleshooting', 'network', 'Follow this order: 1) Check physical connections (cables, power). 2) Check link/activity lights on network equipment. 3) Restart the affected device. 4) Test with ping to isolate whether it''s a local or wider network issue. 5) Check if the problem affects one user or many — many users pointing to a shared device (switch/router) failing. 6) Escalate to Admin if the issue is at the router/core switch level.');
