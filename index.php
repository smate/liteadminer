<?php
// AdminerLite - SQLite Database Manager
global $selectedLang, $lang, $dbFile;

// Configuration
$dbFile = getenv('DB_FILE') ? getenv('DB_FILE') : 'database.sqlite';
$selectedLang = getenv('LANG') ? getenv('LANG') : 'en';

if (!file_exists($dbFile)) {
    die('Database file not found');
}

// Connect to SQLite database
$database = new Database($dbFile);

$action = $_GET['action'] ?? 'database';

$lang['en'] = [
    'None' => 'None',
    'Count' => 'Count',
    'Sum' => 'Sum',
    'Average' => 'Average',
    'Minimum' => 'Minimum',
    'Maximum' => 'Maximum',
    'All' => 'All',
    'Filter' => 'Filter',
    'Execute' => 'Execute',
    'Edit' => 'Edit',
    'Page' => 'Page',
    'Next' => 'Next',
    'Previous' => 'Previous',
    'select' => 'Select',
    'Schema' => 'Schema',
    'Data' => 'Data',
];

if ($action === 'editRow') {
    $tableSchema = $database->getTableSchema($_GET['table']);
    $columnsData = $tableSchema['data'];
    $rowData = $database->getTableData($_GET['table'], 1, 1, [], [], []);
    $formDataView = new FormDataView($rowData['data'][0], $columnsData, $_GET['table']);
} else {
    $tableData = match ($action) {
        'database' => $database->listTables(),
        'tableSchema' => $database->getTableSchema($_GET['table']),
        'tableData' => $database->getTableData(
            $_GET['table'],
            (int) ($_GET['page'] ?? 1),
            (int) ($_GET['perPage'] ?? 20),
            $_GET['where'] ?? [],
            $_GET['select'] ?? [],
            $_GET['sort'] ?? []
        ),
        'sql' => $database->processQuery($_GET['sql'] ?? ''),
    };
    $tableDataView = new TableDataView($tableData, $action === 'tableData');
}

$template = new Template();
$template->render('layout', [
    'title' => 'AdminerLite',
    'tables' => $database->listTables(),
    'contentData' => $action === 'editRow' ? $formDataView->render() : $tableDataView->render(),
]);

class FormDataView
{
    public function __construct(
        private array $rowData,
        private array $columns,
        private string $tableName
    ) {
    }

    public function render(): string
    {
        $primaryKey = array_filter($this->columns, fn($column) => $column['pk'] == 1);
        $html = TableDataView::getTableHeader($this->tableName);
        $html .= '<form class="editForm" method="post">';
        $html .= '<input type="hidden" name="action" value="editRow">';
        $html .= '<input type="hidden" name="table" value="' . $this->tableName . '">';
        foreach ($primaryKey as $name => $value) {
            $html .= $value['name'] . '<input type="text" name="pk[' . $value['name'] . ']" value="' . $this->rowData[$value['name']] . '">';
        }
        $html .= '<table class="tableData">';
        foreach ($this->columns as $name => $value) {
            $html .= '<tr><th class="text-right w-32"><label for="' . $value['name'] . '">' . $value['name'] . '</label></th>';
            $html .= match ($value['type']) {
                'INTEGER' => '<td><input type="number" name="' . $value['name'] . '" value="' . $this->rowData[$value['name']] . '"></td>',
                'TEXT' => '<td><textarea name="' . $value['name'] . '">' . $this->rowData[$value['name']] . '</textarea></td>',
                'BOOLEAN' => '<td><input type="checkbox" name="' . $value['name'] . '" value="' . $this->rowData[$value['name']] . '"></td>',
                default => '<td><input type="text" name="' . $value['name'] . '" value="' . $this->rowData[$value['name']] . '"></td>',
            };
        }
        $html .= '</table>';
        $html .= '<button type="submit">Execute</button>';
        $html .= '</form>';
        return $html;
    }
}

class TableDataView
{
    public bool $editable = false;
    public array $tableData = [];
    public array $columnNames = [];
    public string $tableTitle = '';
    public bool $showFilter = false;
    public int $total = 0;
    public int $page = 1;
    public int $perPage = 1;
    public string $sql = '';
    public bool $showPagination = false;
    public bool $showQuery = false;
    public function __construct(array $tableData, $editable = false)
    {
        global $action;
        if (empty($tableData['data']) && $action !== 'sql') {
            $this->columnNames = ['No data'];
            $this->tableTitle = $tableData['table'] ?? '';
            $this->tableData = [['No data']];
            $this->showQuery = $action === 'sql';
            return;
        }
        $this->tableData = $tableData['data'];
        $this->sql = $tableData['sql'];
        $this->columnNames = array_keys($this->tableData[0] ?? []);
        $this->tableTitle = $tableData['table'] ?? 'SQL';
        $this->total = $tableData['total'] ?? 0;
        $this->page = $tableData['page'] ?? 1;
        $this->perPage = $tableData['perPage'] ?? 1;
        $this->editable = $editable;
        $this->showFilter = $this->perPage > 0 && $action === 'tableData';
        $this->showPagination = $this->total > 0 && $action === 'tableData';
        $this->showQuery = true;
    }

    public static function getTableHeader(string $table): string
    {
        global $action;
        $html = '<h2>' . $table . '</h2>';
        $html .= '<a ' . ($action === 'tableData' ? 'class="selected"' : '') . ' href="' . urlLink('?action=tableData&table=' . $table) . '">' . __('Data') . '</a> ';
        $html .= '<a ' . ($action === 'tableSchema' ? 'class="selected"' : '') . ' href="' . urlLink('?action=tableSchema&table=' . $table) . '">' . __('Schema') . '</a>';
        return $html;
    }

    public function render(): string
    {
        global $action;
        $html = $this->getTableHeader($this->tableTitle);

        if ($this->showFilter) {
            $html .= $this->renderFilter();
        }

        if ($this->showQuery) {
            $html .= $this->renderQuery();
        }

        $html .= $this->renderTable();

        if ($this->showPagination) {
            $html .= $this->renderPagination();
        }

        return $html;
    }

    private function renderFilter(): string
    {
        $html = '<div class="filter">';
        $html .= '<form method="get">';

        $html .= $this->renderSelectFilter();
        $html .= $this->renderWhereFilter();
        $html .= $this->renderSortFilter();

        $html .= '<button type="submit">' . __('Filter') . '</button>';
        $html .= '</form>';
        $html .= '</div>';

        return $html;
    }

    private function renderSelectFilter(): string
    {
        $html = '<fieldset class="filter-col"><legend>Select</legend>';
        $html .= '<select name="select[0][method]">';
        $html .= '<option value="">' . __('None') . '</option>';
        $html .= '<option value="count">' . __('Count') . '</option>';
        $html .= '<option value="sum">' . __('Sum') . '</option>';
        $html .= '<option value="avg">' . __('Average') . '</option>';
        $html .= '<option value="min">' . __('Minimum') . '</option>';
        $html .= '<option value="max">' . __('Maximum') . '</option>';
        $html .= '</select>';
        $html .= '<select name="select[0][column]">';
        $html .= '<option value="">' . __('All') . '</option>';
        foreach ($this->columnNames as $column) {
            $html .= '<option value="' . $column . '">' . $column . '</option>';
        }
        $html .= '</select>';
        $html .= '</fieldset>';

        return $html;
    }

    private function renderWhereFilter(): string
    {
        $html = '<fieldset class="filter-col"><legend>Where</legend>';
        $html .= '<input type="hidden" name="action" value="tableData">';
        $html .= '<input type="hidden" name="table" value="' . $this->tableTitle . '">';
        $html .= '<select name="where[0][column]">';
        $html .= '<option value="">' . __('All') . '</option>';
        foreach ($this->columnNames as $column) {
            $html .= '<option value="' . $column . '" ' . (isset($_GET['where'][0]['column']) && $_GET['where'][0]['column'] === $column ? 'selected' : '') . '>' . $column . '</option>';
        }
        $html .= '</select>';
        $html .= '<select name="where[0][condition]">';
        $conditions = [
            '=' => '=',
            '!=' => '!=',
            '>' => '>',
            '>=' => '>=',
            '<' => '<',
            '<=' => '<='
        ];
        foreach ($conditions as $condition => $name) {
            $html .= '<option value="' . $condition . '" ' . (isset($_GET['where'][0]['condition']) && $_GET['where'][0]['condition'] === $condition ? 'selected' : '') . '>' . $name . '</option>';
        }
        $html .= '</select>';
        $html .= '<input type="text" name="where[0][search]" value="' . ($_GET['where'][0]['search'] ?? '') . '">';
        $html .= '</fieldset>';

        return $html;
    }

    private function renderSortFilter(): string
    {
        $html = '<fieldset class="filter-col"><legend>Sort</legend>';
        $html .= '<select name="sort[0][column]">';
        $html .= '<option value="">' . __('None') . '</option>';
        foreach ($this->columnNames as $column) {
            $html .= '<option value="' . $column . '" ' . (isset($_GET['sort'][0]['column']) && $_GET['sort'][0]['column'] === $column ? 'selected' : '') . '>' . $column . '</option>';
        }
        $html .= '</select>';
        $html .= '<select name="sort[0][direction]">';
        $sortDirections = [
            'ASC' => 'ASC',
            'DESC' => 'DESC'
        ];
        foreach ($sortDirections as $direction => $name) {
            $html .= '<option value="' . $direction . '" ' . (isset($_GET['sort'][0]['direction']) && $_GET['sort'][0]['direction'] === $direction ? 'selected' : '') . '>' . $name . '</option>';
        }
        $html .= '</select>';
        $html .= '</fieldset>';

        return $html;
    }

    private function renderQuery(): string
    {
        $html = '<div class="query">';
        $html .= '<h3>Query</h3>';
        $html .= '<form method="get">';
        $html .= '<input type="hidden" name="action" value="sql">';
        $html .= '<textarea cols="100" rows="3" name="sql">' . $this->sql . '</textarea>';
        $html .= '<button type="submit">' . __('Execute') . '</button>';
        $html .= '</form>';
        $html .= '</div>';

        return $html;
    }

    private function renderTable(): string
    {
        $html = '<table class="tableData">';
        $html .= '<tr>';
        if ($this->editable) {
            $html .= '<th>' . __('Edit') . '</th>';
        }
        foreach ($this->columnNames as $column) {
            $html .= '<th>' . $column . '</th>';
        }
        $html .= '</tr>';
        foreach ($this->tableData as $row) {
            $html .= '<tr>';
            if ($this->editable) {
                $html .= '<td><a href="' . urlLink('?action=editRow&table=' . $this->tableTitle . '&id=' . ($row['id'] ?? reset($row))) . '">' . __('Edit') . '</a></td>';
            }
            foreach ($row as $column) {
                $html .= '<td>' . $column . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    private function renderPagination(): string
    {
        $html = '<div class="pagination">';
        if ($this->page > 1) {
            $html .= '<a href="' . urlLink('?action=tableData&table=' . $this->tableTitle . '&page=' . ($this->page - 1)) . '">' . __('Previous') . '</a>';
        }
        $html .= '<span>' . __('Page') . ' ' . $this->page . ' of ' . ceil($this->total / $this->perPage) . '</span>';
        if ($this->page < ceil($this->total / $this->perPage)) {
            $html .= '<a href="' . urlLink('?action=tableData&table=' . $this->tableTitle . '&page=' . ($this->page + 1)) . '">' . __('Next') . '</a>';
        }
        $html .= '</div>';

        return $html;
    }
}

class Database
{
    private PDO $db;

    public function __construct(string $dbFile)
    {
        try {
            $this->db = new PDO("sqlite:$dbFile");
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public function listTables(): array
    {
        $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table'");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTableData(string $table, int $page = 1, int $perPage = 1, array $where = [], array $select = [], array $sort = []): array
    {
        $offset = ($page - 1) * $perPage;
        // Select
        $sql = "SELECT " . (!empty($select[0]['column']) ? implode(',', array_column($select, 'column')) : '*') . " FROM $table ";
        // Where
        if (!empty($where[0]['column'])) {
            foreach ($where as $condition) {
                $sql .= " WHERE " . $condition['column'] . " " . $condition['condition'] . " '" . $condition['search'] . "'";
            }
        }
        // Sort
        if (!empty($sort[0]['column'])) {
            $sql .= " ORDER BY " . $sort[0]['column'] . " " . $sort[0]['direction'];
        }
        $sql .= " LIMIT $perPage OFFSET $offset";
        
        return $this->processQuery($sql, $table, $page, $perPage);
    }

    public function getTableTotal(string $table): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM $table");
        return (int) $stmt->fetchColumn(0);
    }

    public function executeQuery(string $sql): array
    {
        if (empty($sql)) {
            return [[]];
        }

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo $e->getMessage();
            dd($sql);
        }
    }

    public function getTableSchema(string $table): array
    {
        return $this->processQuery("PRAGMA table_info($table)", $table, 1, 0);
    }

    public function getDatabaseSchema(): array
    {
        $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table'");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function processQuery(string $sql, ?string $table = null, ?int $page = null, ?int $perPage = null): array
    {
        $result = $this->executeQuery($sql);   
        $response = [
            'sql' => $sql,
            'data' => $result,
        ];
        if ($table) {
            $response['table'] = $table;
            $response['total'] = $this->getTableTotal($table);
            $response['page'] = $page;
            $response['perPage'] = $perPage;
        }
        return $response;
    }
}

class Template
{
    public function render(string $template, array $data = []): void
    {
        echo $this->$template($data);
    }

    private function layout(array $data = []): string
    {
        $html = '<html>
        <head>
        <title>AdminerLite</title>
        ' . $this->styles() . '
        </head>
        <body>
        
        <div class="container">
            <div class="sidebar">
            ' . $this->sidebar($data) . '
            </div>
            <div class="content">
            ' . $this->content($data) . '
            </div>
        </div>
        </body>
        </html>';
        return $html;
    }

    private function styles(): string
    {
        return '<style>
        body {
            background-color: #233142;
            color: #e3e3e3;
            font-family: Arial, sans-serif;
            margin: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            font-size: 12px;
        }
        h1, h2, h3 {
            margin: 10px 0 0 0;
        }
        h1 {
            font-size: 1.2em;
        }
        h2 {
            font-size: 1.1em;
        }
        a {
            color: #f95959;
            text-decoration: none;
        }
        ul {
            margin: 0;
        }
        a:hover {
            color: #e0e0e0;
            text-decoration: underline;
        }
        .bold {
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .w-32 {
            width: 16rem;
        }
        .container {
            display: flex;
            width: 100%;
            flex-direction: row;
            height: 100vh; /* Full viewport height */
        }

        .sidebar {
            width: 250px;
            min-width: 250px; /* Prevent shrinking */
            max-width: 250px; /* Prevent expanding */
            padding: 10px;
            box-sizing: border-box; /* Include padding in width calculation */
            overflow: hidden; /* Hide overflowing content */
        }
        .content {
            flex-grow: 1; /* Take up the remaining space */
            padding: 10px;
            overflow-y: auto; /* In case the content overflows */
        }

        .sidebar .tables ul {
            list-style-type: none;
            padding: 0;
        }
        .sidebar .menu ul {
            list-style-type: none;
            padding: 0;
        }
        a.selected {
            color: #e3e3e3;
            text-decoration: underline;
        }
        .sidebar .menu ul li {
        display: inline-block; margin-right: 10px;
        }
        .tableData {
            border-collapse: collapse;
            width: 100%;
            font-size: 0.9rem;
        }
        .tableData th, .tableData td {
            border: 1px solid #ddd;
            padding: 3px 6px;
        }
        .tableData th {
            background-color: #233142;
            color: #e3e3e3
        }
        .tableData tr {
            background-color: #455d7a;
        }
        .tableData tr:hover td{
            background-color: #233142;
            color: #e3e3e3;
        }
        .tableData tr:nth-child(odd) {
            background-color: #354b66;
        }

        .editForm {
            gap: 10px;
        }
        .editForm .form-group {
            display: flex;
            flex-direction: row;
            gap: 5px;
        }
        .editForm .form-group label {
            width: 100px;
            text-align: right;
            padding-right: 5px;
        }
        </style>';
    }

    private function menu(array $data = []): string
    {
        $menu = [
            'SQL' => '?action=sql',
        ];
        $html = '<div class="menu">
        <h1>AdminerLite</h1>
        <ul>';
        foreach ($menu as $name => $link) {
            $html .= '<li><a href="' . urlLink($link) . '">' . $name . '</a></li>';
        }
        $html .= '</ul>
        </div>';
        return $html;
    }

    private function sidebar(array $data = []): string
    {
        return $this->menu($data) . $this->printTables($data);
    }

    private function printTables(array $data = []): string
    {
        $html = '<div class="tables">
        <h2>Tables</h2>
        <ul>';
        foreach ($data['tables'] as $table) {
            $html .= '<li>';
            $html .= '<a href="' . urlLink('?action=tableData&table=' . $table) . '">' . __('select') . '</a> ';
            $html .= "&nbsp;" . '<a ' . (isset($_GET['table']) && $_GET['table'] === $table ? 'class="bold selected"' : 'class="bold"') . ' href="' . urlLink('?action=tableSchema&table=' . $table) . '">' . $table . '</a>';
            $html .= '</li>';
        }
        $html .= '</ul>
        </div>';
        return $html;
    }

    private function content(array $data = []): string
    {
        return $data['contentData'];
    }
}

function urlLink(string $url): string
{
    return $_SERVER['PHP_SELF'] . $url;
}

function dd($data): void
{
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    die;
}

function __(string $string): string
{
    global $lang, $selectedLang;
    if (!isset($lang[$selectedLang][$string])) {
        echo "Lang string not found: $string";
        dd($string);
    }
    return $lang[$selectedLang][$string] ?? $string;
}
