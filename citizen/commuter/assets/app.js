// C:\xampp\htdocs\tmm_gsm\citizen\commuter\assets\app.js

document.addEventListener('DOMContentLoaded', function() {
    
    // AI Auto-Categorization Logic
    const descInput = document.getElementById('complaintDescription');
    const typeSelect = document.getElementById('complaintType');
    
    if (descInput && typeSelect) {
        descInput.addEventListener('input', function() {
            const text = this.value.toLowerCase();
            let category = "";
            
            if (text.match(/speed|fast|race|swerve|cut|beat|red light/)) {
                category = "Reckless Driving";
            } else if (text.match(/charge|price|expensive|fare|change|kupit/)) {
                category = "Overcharging";
            } else if (text.match(/dirty|smell|trash|cockroach|mess/)) {
                category = "Dirty Vehicle";
            } else if (text.match(/rude|shout|fight|harass|catcall|touch/)) {
                category = "Harassment";
            }

            if (category) {
                typeSelect.value = category;
                // Update hidden AI category field
                const aiField = document.getElementById('aiCategoryField');
                if(aiField) aiField.value = category;

                // Visual feedback for AI
                typeSelect.style.border = "2px solid #20c997";
                setTimeout(() => typeSelect.style.border = "1px solid #ced4da", 1000);
            }
        });
    }

    // AI Plate Scanner (Tesseract.js)
    const fileInput = document.getElementById('complaintMedia');
    const plateInput = document.getElementById('plateNumber');
    
    if (fileInput && plateInput) {
        fileInput.addEventListener('change', async function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const aiBadge = document.getElementById('aiStatus');
                
                if(aiBadge) {
                    aiBadge.innerHTML = '🧠 AI Scanning...';
                    aiBadge.style.display = 'inline-block';
                    aiBadge.style.color = '#fd7e14'; // Orange
                }

                // Check if Tesseract is available
                if (typeof Tesseract === 'undefined') {
                    console.error("Tesseract.js not loaded");
                    if(aiBadge) aiBadge.innerHTML = '⚠️ AI Offline';
                    return;
                }

                try {
                    const { data: { text } } = await Tesseract.recognize(
                        file,
                        'eng',
                        { 
                           // logger: m => console.log(m) 
                        }
                    );
                    
                    console.log("OCR Text:", text);

                    // Regex for Philippines Plate Numbers (Standard: LLL-DDDD or LLL-DDD)
                    // Also handles potential OCR errors or variations
                    const plateRegex = /([A-Z]{3}[\s-]?[0-9]{3,4})|([0-9]{3,4}[\s-]?[A-Z]{3})/i;
                    const match = text.toUpperCase().match(plateRegex);

                    if (match) {
                        // Normalize format: ABC 1234 -> ABC-1234
                        let cleanPlate = match[0].replace(/[\s]/g, '-'); 
                        // Ensure dash is present if missing (ABC1234 -> ABC-1234)
                        if (!cleanPlate.includes('-')) {
                             // Naive split based on known formats
                             if (cleanPlate.match(/^[A-Z]{3}[0-9]{3,4}$/)) {
                                 cleanPlate = cleanPlate.substring(0, 3) + '-' + cleanPlate.substring(3);
                             }
                        }

                        plateInput.value = cleanPlate;
                        if(aiBadge) {
                            aiBadge.innerHTML = '🧠 AI Detected: ' + cleanPlate;
                            aiBadge.style.color = '#28a745'; // Green
                        }
                    } else {
                        if(aiBadge) {
                             aiBadge.innerHTML = '🧠 No Plate Detected';
                             aiBadge.style.color = '#dc3545'; // Red
                        }
                    }

                } catch (err) {
                    console.error(err);
                    if(aiBadge) aiBadge.innerHTML = '⚠️ Scan Error';
                }
            }
        });
    }
});

// Helper to show alerts
function showAlert(id, message, type) {
    const el = document.getElementById(id);
    if (el) {
        el.className = `alert alert-${type}`;
        el.textContent = message;
        el.style.display = 'block';
    }
}

// Check Vehicle Function
async function checkVehicle(event) {
    event.preventDefault();
    const plate = document.getElementById('checkPlate').value;
    const resultDiv = document.getElementById('vehicleResult');
    const loader = document.getElementById('checkLoader');

    if(!plate) return;

    loader.style.display = 'inline-block';
    resultDiv.style.display = 'none';
    showAlert('checkAlert', '', 'info');
    document.getElementById('checkAlert').style.display = 'none';

    try {
        const response = await fetch(`api/check_vehicle.php?plate=${encodeURIComponent(plate)}`);
        const data = await response.json();
        
        loader.style.display = 'none';

        if (data.success) {
            resultDiv.innerHTML = `
                <h3>Vehicle Details</h3>
                <p><strong>Plate:</strong> ${data.data.plate_number}</p>
                <p><strong>Cooperative:</strong> ${data.data.coop_name || 'N/A'}</p>
                <p><strong>Route:</strong> ${data.data.route}</p>
                <p><strong>Status:</strong> <span class="status-badge status-${data.data.status}">${data.data.status}</span></p>
            `;
            resultDiv.style.display = 'block';
        } else {
            showAlert('checkAlert', data.message, 'danger');
        }
    } catch (e) {
        loader.style.display = 'none';
        showAlert('checkAlert', 'Error connecting to server', 'danger');
    }
}

// Submit Complaint Function
async function submitComplaint(event) {
    event.preventDefault();
    const form = document.getElementById('complaintForm');
    const formData = new FormData(form);
    const loader = document.getElementById('submitLoader');
    
    loader.style.display = 'inline-block';
    showAlert('complaintAlert', '', 'info');
    document.getElementById('complaintAlert').style.display = 'none';

    try {
        const response = await fetch('api/submit_complaint.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        loader.style.display = 'none';

        if (data.success) {
            showAlert('complaintAlert', `Complaint Submitted! Ref No: ${data.reference_number}`, 'success');
            form.reset();
            // Show track link or something
        } else {
            showAlert('complaintAlert', data.message, 'danger');
        }
    } catch (e) {
        loader.style.display = 'none';
        showAlert('complaintAlert', 'Error submitting complaint', 'danger');
    }
}

// Track Complaint Function
async function trackComplaint(event) {
    event.preventDefault();
    const ref = document.getElementById('trackRef').value;
    const resultDiv = document.getElementById('trackResult');
    const loader = document.getElementById('trackLoader');

    if(!ref) return;

    loader.style.display = 'inline-block';
    resultDiv.style.display = 'none';
    showAlert('trackAlert', '', 'info');
    document.getElementById('trackAlert').style.display = 'none';

    try {
        const response = await fetch(`api/track_complaint.php?ref=${encodeURIComponent(ref)}`);
        const data = await response.json();
        
        loader.style.display = 'none';

        if (data.success) {
            resultDiv.innerHTML = `
                <h3>Status: <span class="status-badge status-${data.data.status.split(' ')[0]}">${data.data.status}</span></h3>
                <p><strong>Reference:</strong> ${data.data.reference_number}</p>
                <p><strong>Type:</strong> ${data.data.complaint_type}</p>
                <p><strong>Date:</strong> ${data.data.created_at}</p>
            `;
            resultDiv.style.display = 'block';
        } else {
            showAlert('trackAlert', data.message, 'danger');
        }
    } catch (e) {
        loader.style.display = 'none';
        showAlert('trackAlert', 'Error tracking complaint', 'danger');
    }
}
