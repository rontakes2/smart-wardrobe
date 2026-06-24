# Smart Wardrobe

Smart Wardrobe is a personal digital closet management system designed to help users catalog their clothing, build outfits, and manage their style preferences. It includes cloud integration for syncing items from Google Drive and Google Photos, alongside a secure administrative dashboard for system monitoring.

## Dependencies
* **PHP:** Version 8.0 or higher.
* **Database:** MySQL or MariaDB. 
* **Server:** A local web server (e.g., XAMPP, WAMP, MAMP, or Laravel Herd).
* **Composer:** Required to manage PHP dependencies (specifically `google/apiclient` and `vlucas/phpdotenv`).

## Installation
1.  **Clone the repo:**
    ```bash
    git clone https://github.com/rontakes2/smart-wardrobe
    cd smart-wardrobe
    ```
2.  **Install dependencies:**
    Run Composer to install the Google API client & other necessary packages:
    ```bash
    composer install
    ```
3.  **Database setup:**
    * Create a new MySQL database.
    * Import SQL schema file.
    * Configure `includes/db.php` with your database host, name, username, and password.
4.  **Configure environment variables**
    Create a `.env` file in the root directory to manage sensitive credentials:
    ```text
    GOOGLE_CLIENT_ID=_client_id_
    GOOGLE_CLIENT_SECRET=_client_secret_
    ```
5.  **Run:**
    * Move the project folder to your server's root directory (e.g., C:\xampp\htdocs).
    * Start your Apache and MySQL servers.
    * Navigate to `http://localhost/smart-wardrobe/login.php` in your browser.

## Configuration Note
For Google Drive and Photos imports to function, you must register appl in the [Google Cloud Console](https://console.cloud.google.com/), enable the **Google Drive API** and **Photos Library API**, and ensure your Redirect URI is set to:
`http://localhost/smart-wardrobe/google_sync.php?action=callback`

## License

This project is licensed under the Commercial Restricted License (CRL) v1.1.
See [LICENSE.md](LICENSE.md) for details.

For commercial use, please contact itegi.ronald@strathmore.edu, kubai.kindness@strathmore.edu for licensing options.