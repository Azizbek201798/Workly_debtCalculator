<?php

class Workly {

    public $username;
    public $password;
    public $dbname;
    public $host;
    public $pdo;
    public $total_debt;

    public function __construct($username, $password, $dbname, $host) {
        $this->username = $username;
        $this->password = $password;
        $this->dbname = $dbname;
        $this->host = $host;
    }

    public function connect() {
        try {
            $this->pdo = new PDO("mysql:host=$this->host;dbname=$this->dbname", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
        }
    }

    public function fetchAllRows() {
        try {
            $query = "SELECT * FROM daily";
            $stmt = $this->pdo->query($query);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $rows;
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }

  public function updateWorkedOff() {
    try {
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $query = "UPDATE daily SET worked_off = 0 WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(":id", $id);
            $stmt->execute();
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

    public function insertData($arrivedAt, $leavedAt, $requiredWork, $workedOff) {
        try {
            $query = "INSERT INTO daily (arrived_at, leaved_at, required_work, worked_off) VALUES (:arrivedAt, :leavedAt, :requiredWork, :workedOff);";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(":arrivedAt", $arrivedAt->format('Y-m-d H:i:s')); 
            $stmt->bindValue(":leavedAt", $leavedAt->format('Y-m-d H:i:s'));
            $stmt->bindValue(":requiredWork", $requiredWork);
            $stmt->bindValue(":workedOff", $workedOff);
            $stmt->execute();
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }

}

$data = new Workly('root','root','Workly','localhost');
$data->connect();
$rows = $data->fetchAllRows();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $arrivedAt = new DateTime($_POST['arrived_at']);
    $leavedAt = new DateTime($_POST['leaved_at']);
    
    if ($leavedAt < $arrivedAt) {
        
        $requiredWork = 0;
    
    } else {
        $duration = $leavedAt->getTimestamp() - $arrivedAt->getTimestamp();;
        $intervalInSeconds = $duration;
        
        if ($intervalInSeconds > 32400){
            $requiredWork = 0;
        } else {
            $requiredWork = 32400 - $intervalInSeconds;
        }
    }

    if ($requiredWork > 0) {
        $workedOff = 1;
    } else {
        $workedOff = 0;
    }

    $data->insertData($arrivedAt, $leavedAt, $requiredWork, $workedOff);

    header("Location: {$_SERVER['PHP_SELF']}");
    exit();

}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $data->updateWorkedOff($id);

    header("Location: {$_SERVER['PHP_SELF']}");
    exit();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>PWOT - Personal Work Off Tracker</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <h1 class="text-center mb-10">PWOT - Personal Work Off Tracker</h1>

    <form method="post" action="" class="mb-4">
    <div class="form-row">
        <div class="form-group col-md-4">
            <label for="arrived_at">Arrived at</label>
            <input type="datetime-local" class="form-control" name="arrived_at" required>
        </div>
        <div class="form-group col-md-4">
            <label for="leaved_at">Leaved at</label>
            <input type="datetime-local" class="form-control" name="leaved_at" required>
        </div>
        <div class="form-group col-md-4 align-self-end">
            <button type="submit" class="btn btn-primary">Submit</button>
        </div>
    </div>
</form>

    <table class="table table-bordered">
    <thead class="thead-dark">
        <tr>
            <th>#</th>
            <th>Arrived at</th>
            <th>Leaved at</th>
            <th>Required work off</th>
            <th>Worked off</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!empty($rows)) : ?>
    <?php
    $total_debt = 0;

    foreach ($rows as $index => $row) {

        $arrived_at = new DateTime($row['arrived_at']);
        $leaved_at = new DateTime($row['leaved_at']);
        $requiredWorkHours = DateTime::createFromFormat('H:i:s', '09:00:00');
        $debtInSeconds = $row['required_work'];

        if ($row['worked_off'] == 1){
            $total_debt += $row['required_work'];
        }

        ?>
        <tr>
            <td><?php echo $index + 1; ?></td>
            <td><?php echo $arrived_at->format('Y-m-d H:i:s'); ?></td>
            <td><?php echo $leaved_at->format('Y-m-d H:i:s'); ?></td>
            <td><?php
                if ($debtInSeconds > 0) {
                    if (($debtInSeconds / 60) >= 60) {
                        echo (int)($debtInSeconds / 3600) . ' hours and ' . (int)(($debtInSeconds % 3600) / 60) . " min";
                    } else {
                        echo (int)($debtInSeconds / 60) . " min";
                    }
                } else {
                    echo "0 min.";
                }
                ?></td>
            <td>
            <?php if ($debtInSeconds > 0) : ?>
                    <button type="button" class="btn btn-primary" onclick="confirmAction(this)">Done</button>
                    <input type="checkbox" class="done-checkbox" style="display: none;">
                <?php else : ?>
                    <input type="checkbox" checked disabled>
                <?php endif; ?>
            </td>
        </tr>
    <?php } ?>
<?php endif; ?>
    </tbody>
</table>
    <h5>Total Work Of Time : <?php echo (int)($total_debt / 3600) . ' hours and ' . (int)(($total_debt%3600) / 60) . " min"?> </h5>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>

function confirmAction(button) {
    var confirmed = confirm("Are you sure?");
    if (confirmed) {
        var checkbox = button.nextElementSibling;
        checkbox.checked = true;
        button.style.display = 'none';
        checkbox.style.display = 'inline';

        var row = button.closest('tr');
        row.style.backgroundColor = "#A2E4A4";
    }
}

</script>

</body>
</html>
