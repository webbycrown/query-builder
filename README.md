# Laravel Query Builder Package

## Introduction

### Simplified Dynamic Report Generation
The **Query Builder Package** is designed to make report generation easy for both developers and non-technical users. Writing SQL queries manually can be complex and time-consuming, especially when handling multiple tables, conditions, and calculations. This package simplifies the process with an intuitive interface that allows users to build queries without needing advanced database knowledge. By selecting tables, adding filters, applying conditions, and grouping data, users can generate custom reports effortlessly. This improves efficiency, reduces manual coding, and minimizes errors, ensuring quick and accurate data retrieval.

The primary goal of the **Query Builder Package** is to empower developers by providing a robust and efficient tool for generating reports dynamically. Traditional SQL query writing can be time-consuming and complex, especially when dealing with multiple joins, conditions, and aggregations. This package simplifies the process by offering an intuitive interface where developers can construct queries without deep SQL knowledge. It enhances productivity by reducing manual coding efforts, minimizing errors, and enabling faster report generation. With built-in support for selecting tables, applying conditions, joining tables, and grouping results, this package ensures that developers can retrieve and display relevant data efficiently.

### Purpose of the Query Builder Package
The **Laravel Query Builder Package** was created to provide developers with a streamlined and flexible way to generate dynamic reports. Instead of manually writing SQL queries, this package enables developers to construct queries using an intuitive interface, saving time and reducing errors. The goal is to enhance productivity by offering a user-friendly solution for building complex reports without requiring deep SQL expertise.

### Add Query Screen
![Add Query Screen](https://github.com/user-attachments/assets/76bd3f66-8b52-412a-9e96-ce64b4409075)

### Query List Screen
![Query List Screen](https://github.com/user-attachments/assets/1a71130f-5129-448b-8914-546c3ef007ff)

### View Query Screen
![View Query Screen](https://github.com/user-attachments/assets/a913b485-4ce6-4ec3-a000-eb03e8f0a108)

### Scheduling Reports List Screen
![Scheduling Reports List Screen](https://github.com/user-attachments/assets/b8b2dcd9-c629-42ba-8854-f537ad851947)

### Add Scheduled Report Screen
![Scheduling Form Screen](https://github.com/user-attachments/assets/01efa794-7f6d-4b44-ab8b-4db956f96aaf)

### Log List Screen
![Log List Screen](https://github.com/user-attachments/assets/8065d172-0ed9-44fe-b542-cb51bc310ad4)

### Log JSON Screen
![Log JSON Screen](https://github.com/user-attachments/assets/e5e0621f-91da-4c78-988b-eef21dc9b3ff)

## Features
- Select main table for querying
- Add optional join tables (only left join)
- Choose specific columns to display
- Apply conditions using operators and values
- Group results by columns with aggregation functions (SUM, GROUP_CONCAT, etc.)
- Assign alias names for grouped data
- Save, edit, delete, and execute queries dynamically
- Support additional functions like AVG(), MIN(), MAX(), COUNT(), etc
- Add advanced filtering options such as HAVING and BETWEE
- Implement ORDER BY functionality to sort query results in ASC/DESC order
- Limit & Offset – Allow users to control the number of rows retrieved in query results
- Configure table and column visibility based on settings
- Automatically load language files from the package
- Maintain a history of all executed queries with timestamps and user info
- Automate scheduled report generation with email delivery
- Support advanced condition logic using AND / OR groupings
- Introduce alternate UI screens to enhance usability and workflow

## Installation
```bash
composer require webbycrown/query-builder:dev-main
```
## Configuration
### 1. Publish Config File
Run the following command to publish the query builder configuration file:
```bash
php artisan vendor:publish --tag=config
```
### 2. Publishing Translations
If your application includes language files for translations, you can publish the translation files using the following command:

```php artisan vendor:publish --tag=translations```

This will copy all the translation files from the vendor package to your resources/lang directory, where you can modify them as needed.

## Run Migrations
Before using the package, run the following command to migrate the required database tables:
```bash
php artisan migrate --path=vendor/webbycrown/query-builder/src/Database/migrations
```
## ENV Settings
### 1. Database Configuration for Query Management
Set the following variables in your `.env` file to configure the database used for creating and managing queries:
```
QDB_CONNECTION=mysql
QDB_HOST=127.0.0.1
QDB_PORT=3306
QDB_DATABASE=
QDB_USERNAME=
QDB_PASSWORD=
```

### 2. Table & Column Display Options
The format for displaying table and column names can be adjusted using:
```
QDB_LABEL_MODE="Both"
```
Available Options:
- **Comment**: Displays only the descriptive comment assigned to the table or column, if available. Useful for providing user-friendly labels.
- **Column**: Shows the actual database table name or column name as stored in the schema. Ideal for developers who prefer raw database references.
- **Both** (default): Displays both the original database name and its corresponding comment (if available), providing full context for better understanding.

## Usage
### 1. Access URL
You can access the query builder at:
```bash
http://127.0.0.1:8000/queries
```
### 2. Middleware and Route Prefix
Modify config/querybuilder.php to set middleware and route prefix:
```
return [
    'middleware' => ['web', 'auth'],   // Middleware for all QueryBuilder routes
    'access_route' => 'queries',       // Prefix for web routes
    'log_page_view' => true,           // Show Log Page link if true
    'reports_page_view' => true,       // Show Reports Page link if true
];
```

### 3. Create a Query
Navigate to **Add Query** page and configure:
- **Main Table**: Select the table to query from.
- **Joining Tables** (Optional): Define relationships.
- **Select Columns**: Choose columns to fetch.
- **Conditions**: Set WHERE clauses.
- **Group By**: Apply GROUP BY with aggregation.
- **Save Query**: Store query for future use.
- **Custom Conditions** : Add advanced filtering options such as HAVING and BETWEEN.
- **Grouping & Aggregations** : Support additional functions like AVG(), MIN(), MAX(), COUNT(), etc.
- **Sorting & Ordering** : Implement ORDER BY functionality to sort query results in ASC/DESC order.
- **Limit & Offset** : Allow users to control the number of rows retrieved in query results.
- **Export Options** : Enable downloading reports in multiple formats, including CSV, Excel, PDF, and JSON.

### 4. View Saved Queries
Saved queries can be accessed in the **Query Lists**.

### 5. Edit or Delete Queries
Modify or remove queries from the list.

### 6. Run Queries
Click on a saved query to view the output in a table format.


## Scheduled Report Command
To manually register and bind your custom scheduled reports command, follow these steps:

### 1. Register the Command in `AppServiceProvider.php`

Instead of relying on PSR-4 autoload or Laravel's default auto-discovery, you can manually bind and register the command by modifying the `AppServiceProvider.php` file.

- Open `app/Providers/AppServiceProvider.php`.
- Add the following code inside the `boot()` method:

```php
use Webbycrown\QueryBuilder\Console\Commands\GenerateScheduledReports;

public function boot(): void
{
    if ($this->app->runningInConsole()) {
        $this->commands([
            GenerateScheduledReports::class,
        ]);
    }
}
```
### 2. Verify the Command is Registered
After registering the command, you can verify that it's properly registered by running the following command:

```php artisan list | grep reports```
This should display the reports:generate command if it has been correctly registered.

### 3. Run the Command
To run the command manually, use:

```php artisan reports:generate```

### 4. Scheduling the Command
If you want to schedule the command to run periodically (e.g., every minute), you can use Laravel's task scheduler. Open the bootstrap/app.php file and add the following scheduling logic:

```
->withSchedule(function (Schedule $schedule) {
    $schedule->command('query-builder:generate-scheduled-reports')->everyMinute();
}) 
```
Make sure your system’s cron job is configured to run Laravel's scheduler every minute. You can set this up by adding the following line to your system's crontab file:

```
* * * * * php /path/to/your/project/artisan schedule:run >> /dev/null 2>&1
```

### 5. Create a Scheduled Report
Navigate to the **Add Scheduled Report** page and configure:
- **Report Type**: Select the report you want to schedule from the list of saved queries.
- **Frequency**: Choose how often the report should be sent: Daily, Weekly, or Monthly.
- **Delivery Time**: Select the exact time the report should be delivered (24-hour format).
- **To Email**: Enter the recipient’s email address (comma-separated if multiple).
- **CC Email**: (Optional) Enter any additional recipients to be CC'd.
- **BCC Email**: (Optional) Enter recipients to be BCC'd.
- **Subject**: Provide a custom subject line for the email. Leave empty to use the default.
- **File Format**: Choose the report format: PDF, XLSX, or CSV.
- **Record Limit**: Set a limit on the number of records included in the report (e.g., 1000).
- **Email Body**: Add custom content to appear in the body of the email. You can use HTML or plain text.
- **Active**: Enable or disable the schedule using this checkbox. Only active schedules will be executed.

### 6. View Saved Scheduled Reports
Saved scheduled report can be accessed in the **Scheduled Report List**.

### 7. Edit or Delete Scheduled Reports
Modify or remove scheduled report from the list.


## Upcoming Features
The Query Builder Package is continuously evolving to provide more flexibility and ease of use. Here are some planned features for future updates:

1. **Subquery Support** – Allow users to create nested queries within the main query.


### Contributor Suggestions
We welcome community contributions! If you have ideas for new features or improvements, feel free to submit a pull request or open an issue on our repository.The Query Builder Package is continuously evolving to provide more flexibility and ease of use. Here are some planned features for future updates:

## License
This package is open-source and licensed under the MIT License.