<?php include 'includes/header.php'; ?>

<a href="index.php" class="btn btn-secondary" style="margin-bottom: 1rem;">&larr; Back to Home</a>

<div class="card">
    <h2>Submit a Complaint</h2>
    <p>Report issues safely. Your identity can remain anonymous.</p>
    
    <form id="complaintForm" onsubmit="submitComplaint(event)">
        <input type="hidden" name="ai_category" id="aiCategoryField">
        <div class="form-group">
            <label for="plateNumber">Vehicle Plate Number <span id="aiStatus" class="ai-badge" style="display:none;">🧠 AI Ready</span></label>
            <input type="text" name="plate_number" id="plateNumber" class="form-control" placeholder="e.g. ABC-1234">
        </div>

        <div class="form-group">
            <label for="complaintDescription">Description (AI Auto-Categorization Active)</label>
            <textarea name="description" id="complaintDescription" class="form-control" rows="4" placeholder="Describe the incident (e.g. 'Driver was speeding and swerving')..." required></textarea>
        </div>

        <div class="form-group">
            <label for="complaintType">Complaint Type</label>
            <select name="complaint_type" id="complaintType" class="form-control" required>
                <option value="">-- Select Issue --</option>
                <option value="Overcharging">Overcharging</option>
                <option value="Reckless Driving">Reckless Driving</option>
                <option value="Dirty Vehicle">Dirty Vehicle</option>
                <option value="Harassment">Harassment</option>
                <option value="Refusal to Convey">Refusal to Convey</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <div class="form-group">
            <label for="complaintMedia">Upload Photo/Video (Optional - AI will scan for plate)</label>
            <input type="file" name="media" id="complaintMedia" class="form-control" accept="image/*,video/*">
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_anonymous" checked> Submit Anonymously
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Submit Complaint</button>
    </form>

    <div id="submitLoader" class="loader" style="display:none; margin-top: 1rem;"></div>
    <div id="complaintAlert" class="alert" style="margin-top: 1rem;"></div>
</div>

<!-- Tesseract.js for OCR -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

<?php include 'includes/footer.php'; ?>
