<?php
session_start();
require '../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../signin.php");
    exit;
}

// ADD PLAN
if (isset($_POST['add_plan'])) {

    $name = $_POST['name'];
    $duration = (int)$_POST['duration'];
    $price = (int)$_POST['price'];
    $priority = (int)$_POST['priority'];

    $conn->query("
        INSERT INTO ad_plans (name, duration_days, price, priority_score)
        VALUES ('$name', $duration, $price, $priority)
    ");
}

// DELETE PLAN
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM ad_plans WHERE id=$id");
}

// UPDATE PLAN
if (isset($_POST['update_plan'])) {

    $id = (int)$_POST['id'];
    $name = $_POST['name'];
    $duration = (int)$_POST['duration'];
    $price = (int)$_POST['price'];
    $priority = (int)$_POST['priority'];

    $conn->query("
        UPDATE ad_plans 
        SET name='$name', duration_days=$duration, price=$price, priority_score=$priority
        WHERE id=$id
    ");
}

// FETCH
$plans = $conn->query("SELECT * FROM ad_plans ORDER BY priority_score ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Plans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
    <style>
        body { 
            background-color: #0e0f11;
            color: #fff;
        }

        .card-custom {
            background: #121212;
            border: 1px solid #262626;
            border-radius: 12px;
        }

        .text-purple {
            color: #9526F3;
            font-weight: 600;
        }

        .form-control {
            background: #121212;
            border: 1px solid #262626;
            color: #fff;
        }

        .form-control::placeholder {
            color: #aaa;
        }

        /* BUTTON */
        .btn-purple {
            background: transparent;
            border: 2px solid #9526F3;
            border-radius: 25px;
            padding: 6px 24px;
            color: #9526F3;
            position: relative;
            overflow: hidden;
            transition: 0.3s;
        }

        .btn-purple:hover {
            color: #fff;
            box-shadow: 0 0 12px rgba(149, 38, 243, 0.6);
        }

        .btn-purple::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #9526F3, #7a1fd6, #b44cff);
            transform: scaleX(0);
            transform-origin: left;
            transition: 0.4s;
            z-index: 0;
        }

        .btn-purple span {
            position: relative;
            z-index: 2;
        }

        .btn-purple:hover::before {
            transform: scaleX(1);
        }

        table {
            border-radius: 10px;
            overflow: hidden;
        }
    </style>
<body>

<div class="container mt-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-white">Manage Ad Plans</h4>

        <!-- BACK BUTTON -->
        <a href="dashboard.php" class="btn btn-purple">
            <span>Back</span>
        </a>
    </div>

    <!-- ADD PLAN FORM -->
    <div class="card card-custom p-3 mb-4">
        <form method="POST" class="row g-3">

            <div class="col-md-3">
                <input type="text" name="name" class="form-control" placeholder="Plan Name" required>
            </div>

            <div class="col-md-2">
                <input type="number" name="duration" class="form-control" placeholder="Days" required>
            </div>

            <div class="col-md-2">
                <input type="number" name="price" class="form-control" placeholder="Price ₹" required>
            </div>

            <div class="col-md-2">
                <input type="number" name="priority" class="form-control" placeholder="Priority" required>
            </div>

            <div class="col-md-3">
                <button name="add_plan" class="btn btn-purple w-100">
                    <span>Add Plan</span>
                </button>
            </div>

        </form>
    </div>

    <!-- TABLE -->
    <div class="card card-custom p-3">
        <div class="table-responsive">
            <table class="table table-dark table-bordered text-center align-middle mb-0">

                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Days</th>
                        <th>Price</th>
                        <th>Priority</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
<?php while($row = $plans->fetch_assoc()) { ?>

<tr>
<form method="POST">

    <input type="hidden" name="id" value="<?= $row['id'] ?>">

    <td>
        <input type="text" name="name" value="<?= $row['name'] ?>" class="form-control">
    </td>

    <td>
        <input type="number" name="duration" value="<?= $row['duration_days'] ?>" class="form-control">
    </td>

    <td>
        <input type="number" name="price" value="<?= $row['price'] ?>" class="form-control">
    </td>

    <td>
        <input type="number" name="priority" value="<?= $row['priority_score'] ?>" class="form-control">
    </td>

    <td class="d-flex gap-2 justify-content-center">

        <button name="update_plan" class="btn btn-success btn-sm">
            Update
        </button>

        <a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm">
            Delete
        </a>

    </td>

</form>
</tr>

<?php } ?>
</tbody>

            </table>
        </div>
    </div>
</div>
</body>
</html>