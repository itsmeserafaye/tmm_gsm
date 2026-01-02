<?php include 'includes/header.php'; ?>

<a href="index.php" class="btn btn-secondary" style="margin-bottom: 1rem;">&larr; Back to Home</a>

<div class="card">
    <h2>Track Complaint Status</h2>
    <p>Enter your Reference Number to check the status of your complaint.</p>
    
    <form onsubmit="trackComplaint(event)">
        <div class="form-group">
            <label for="trackRef">Reference Number</label>
            <input type="text" id="trackRef" class="form-control" placeholder="e.g. COM-2025..." required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Track Status</button>
    </form>

    <div id="trackLoader" class="loader" style="display:none; margin-top: 1rem;"></div>
    <div id="trackAlert" class="alert" style="margin-top: 1rem;"></div>

    <div id="trackResult" class="vehicle-info" style="display:none;">
        <!-- Results will appear here -->
    </div>
</div>

<?php include 'includes/footer.php'; ?>
