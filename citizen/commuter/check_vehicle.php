<?php include 'includes/header.php'; ?>

<a href="index.php" class="btn btn-secondary" style="margin-bottom: 1rem;">&larr; Back to Home</a>

<div class="card">
    <h2>Check Vehicle Status</h2>
    <p>Enter the vehicle plate number to verify its registration and franchise details.</p>
    
    <form onsubmit="checkVehicle(event)">
        <div class="form-group">
            <label for="checkPlate">Plate Number</label>
            <input type="text" id="checkPlate" class="form-control" placeholder="e.g. ABC-1234" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Verify Vehicle</button>
    </form>

    <div id="checkLoader" class="loader" style="display:none; margin-top: 1rem;"></div>
    <div id="checkAlert" class="alert" style="margin-top: 1rem;"></div>

    <div id="vehicleResult" class="vehicle-info" style="display:none;">
        <!-- Results will appear here -->
    </div>
</div>

<?php include 'includes/footer.php'; ?>
