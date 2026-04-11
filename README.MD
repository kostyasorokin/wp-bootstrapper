# WP Bootstrapper

![Code Quality](https://github.com/kostyasorokin/wp-bootstrapper/actions/workflows/code-quality.yml/badge.svg)
![PHP Version](https://img.shields.io/badge/PHP-8.4+-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.7+-23282d.svg)
![License](https://img.shields.io/badge/License-GPLv3-green.svg)
![Architecture](https://img.shields.io/badge/Architecture-OOP%20%7C%20PSR--4-orange.svg)

**WP Bootstrapper** is a foundational toolkit designed for modern WordPress development. It replaces the traditional "spaghetti-style" plugin initialization with a clean, attribute-driven architecture, focusing on performance, security, and developer experience.

---

## 🚀 Core Philosophy

Unlike traditional plugins that load dozens of files and hooks on every request, WP Bootstrapper uses a **Lazy-Loading Attribute System**.

* **Zero Overhead:** Hooks are registered only when needed, using a high-performance static cache.
* **Modern PHP 8.4+:** Leverages the latest language features like Attributes, Readonly properties, and Property Hooks.
* **Fluent Configuration:** Features a robust, DTO-based settings API for building complex admin interfaces with ease.

## 🛠 Tech Stack

- **Architecture:** PSR-4 Autoloading, Reflection API, and static Hook Caching.
- **Attributes:** Custom `#[Hook]` attribute for elegant action/filter registration.
- **Dependency Management:** Fully integrated with Composer.
- **Settings Engine:** A custom-built Fluent API with Tab and Section support.

## 🛠 Installation

1. Install the Plugin
   Choose one of the following methods to get the plugin files:

##### Option A: Manual Upload (Recommended)
* Download the plugin as a `.zip` file.
* Upload the extracted folder to the `/wp-content/plugins/` directory.

##### Option B: Via Git (For Developers)
* Clone or download the repository into your WordPress plugins folder:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/kostyasorokin/wp-bootstrapper.git
  ```
2. Activate the plugin through the 'Plugins' menu in WordPress.

