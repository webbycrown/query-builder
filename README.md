# Laravel Query Builder Package

## Introduction

### Simplified Dynamic Report Generation
The **Query Builder Package** is designed to make report generation easy for both developers and non-technical users. Writing SQL queries manually can be complex and time-consuming, especially when handling multiple tables, conditions, and calculations. This package simplifies the process with an intuitive interface that allows users to build queries without needing advanced database knowledge. By selecting tables, adding filters, applying conditions, and grouping data, users can generate custom reports effortlessly. This improves efficiency, reduces manual coding, and minimizes errors, ensuring quick and accurate data retrieval.

The primary goal of the **Query Builder Package** is to empower developers by providing a robust and efficient tool for generating reports dynamically. Traditional SQL query writing can be time-consuming and complex, especially when dealing with multiple joins, conditions, and aggregations. This package simplifies the process by offering an intuitive interface where developers can construct queries without deep SQL knowledge. It enhances productivity by reducing manual coding efforts, minimizing errors, and enabling faster report generation. With built-in support for selecting tables, applying conditions, joining tables, and grouping results, this package ensures that developers can retrieve and display relevant data efficiently.

### Purpose of the Query Builder Package
The **Laravel Query Builder Package** was created to provide developers with a streamlined and flexible way to generate dynamic reports. Instead of manually writing SQL queries, this package enables developers to construct queries using an intuitive interface, saving time and reducing errors. The goal is to enhance productivity by offering a user-friendly solution for building complex reports without requiring deep SQL expertise.

### Add Query Screen
![Add Query Screen](https://github.com/user-attachments/assets/c5111ee8-8d8e-4a4f-aa98-da3e4e93b2ad)

### Query List Screen
![Query List Screen](https://github.com/user-attachments/assets/6fa913e9-acdc-4751-849a-e2c403eb37fd)

### View Query Screen
![View Query Screen](https://github.com/user-attachments/assets/a913b485-4ce6-4ec3-a000-eb03e8f0a108)

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

## Installation
```bash
composer require webbycrown/query-builder:dev-main
```

## Publish Configuration File
Run the following command to publish the query builder configuration file:
```bash
php artisan vendor:publish --tag=config
```

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
- **Custom Conditions** – Add advanced filtering options such as HAVING and BETWEEN.
- **Grouping & Aggregations** – Support additional functions like AVG(), MIN(), MAX(), COUNT(), etc.
- **Sorting & Ordering** – Implement ORDER BY functionality to sort query results in ASC/DESC order.
- **Limit & Offset** – Allow users to control the number of rows retrieved in query results.
- **Export Options** – Enable downloading reports in multiple formats, including CSV, Excel, PDF, and JSON.

### 4. View Saved Queries
Saved queries can be accessed in the **Query Lists**.

### 5. Edit or Delete Queries
Modify or remove queries from the list.

### 6. Run Queries
Click on a saved query to view the output in a table format.

## Upcoming Features
The Query Builder Package is continuously evolving to provide more flexibility and ease of use. Here are some planned features for future updates:

1. **Complex condition building** – Support AND/OR logic for advanced queries.
2. **Subquery Support** – Allow users to create nested queries within the main query.
3. **Scheduling Reports** – Automate report generation and email delivery.
4. **Audit Logs & Query Tracking** – Keep a history of all executed queries with timestamps and user details. 
5. **Will Add 2 Screen (Variant)** – Introduced two new UI screens to enhance user experience and workflow.  
6. **Load Translations from Package** – Automatically load language files from the package.

### Contributor Suggestions
We welcome community contributions! If you have ideas for new features or improvements, feel free to submit a pull request or open an issue on our repository.The Query Builder Package is continuously evolving to provide more flexibility and ease of use. Here are some planned features for future updates:

## License
This package is open-source and licensed under the MIT License.