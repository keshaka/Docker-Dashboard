# 🚀 Docker VPS UI  
### A Self-Hosted Lightweight Docker Management Dashboard

Docker VPS UI is a modern, minimal, and resource-friendly Docker management web UI built using **PHP**, **HTML**, **CSS**, and **JavaScript**.  
It is designed for VPS environments where running heavy containerized dashboards (Portainer, Dockge, Yacht, etc.) is unnecessary.

This dashboard provides a Docker Desktop–style interface directly on your server — no database, no Node.js, no containers required.

---

## ✨ Features

### 🧱 Containers
- List containers
- Start / Stop / Restart / Remove
- View logs and stats
- Auto-refresh support

### 📦 Images
- View local images
- Pull new images
- Remove images
- Inspect metadata

### 💾 Volumes
- See all volumes
- Remove & prune unused volumes
- Inspect volume metadata

### 🌐 Networks
- View networks
- Create / Remove networks
- Inspect configuration
- Prune unused networks

### 📚 Compose Projects
- Create compose project folders
- Upload `docker-compose.yml`
- Start / Stop / Down stacks
- View compose logs

### 🧩 System
- Docker version info
- Host CPU, RAM, disk usage
- Docker disk usage (`docker system df`)
- Prune system/images/containers/volumes

### ⚙ Settings
- Password protection (hashed & stored securely)
- Theme toggle (light/dark)
- Reset UI layout
- About section

### 🖥 Overview Dashboard
- Docker engine version
- Running containers & images count
- System (RAM/CPU/Disk) usage
- Docker usage summary
- Host information

---

## 📂 Directory Structure

├── index.php # New overview dashboard
├── containers.php
├── images.php
├── volumes.php
├── networks.php
├── compose.php
├── system.php
├── settings.php
├── run.php # Executes prune/system commands
├── nav.php # Sidebar layout
├── assets/
│ └── style.css # UI theme
├── compose-projects/ # Created automatically for compose stacks
└── dashboard-settings.json # Auto-created for security settings


---

## 🧰 Requirements

- Linux server (Ubuntu/Debian/CentOS/etc.)
- Docker Engine installed
- Apache or Nginx serving PHP
- PHP 7.4+ with:
  - `php-cli`
  - `php-json`
  - `php-shell_exec` enabled
- PHP user must be allowed to run Docker commands:

```bash
sudo usermod -aG docker www-data
sudo systemctl restart apache2

🔧 Installation
1. Clone or upload the dashboard into your web root:
/var/www/html

2. Set correct permissions
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 775 /var/www/html

3. Create compose projects directory
sudo mkdir -p /opt/compose-projects
sudo chown -R www-data:www-data /opt/compose-projects
sudo chmod -R 775 /opt/compose-projects

4. Ensure PHP can run Docker
sudo usermod -aG docker www-data
sudo systemctl restart apache2

5. Access via browser
http://your-server-ip/
