<?php
// modules/recruitment/job-applications.php

$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

// Get job details
$job = null;
if ($job_id) {
    $stmt = $pdo->prepare("SELECT * FROM job_postings WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();
}

// Get applications
$applications = [];
if ($job_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM job_applications 
        WHERE job_posting_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$job_id]);
    $applications = $stmt->fetchAll();
}

$page_title = $job ? 'Applications for ' . $job['title'] : 'Job Applications';
?>

<div class="recent-expenses-unique">
    <div class="expenses-header">
        <h2>
            <a href="?page=recruitment&subpage=job-posting" style="color: #0e4c92; text-decoration: none;">
                <i class="fas fa-arrow-left"></i>
            </a>
            <?php echo $page_title; ?>
        </h2>
        <span class="category-badge"><?php echo count($applications); ?> applications</span>
    </div>
    
    <?php if (empty($applications)): ?>
    <div style="text-align: center; padding: 60px; color: #95a5a6;">
        <i class="fas fa-file-alt" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
        <h3 style="margin-bottom: 10px;">No Applications Yet</h3>
        <p>Share the application link to start receiving applications.</p>
    </div>
    <?php else: ?>
    <div class="table-container">
        <table class="unique-table">
            <thead>
                <tr>
                    <th>Application #</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Applied Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($app['application_number']); ?></strong>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                    </td>
                    <td>
                        <div style="font-size: 12px;">
                            <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($app['email']); ?></div>
                            <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($app['phone']); ?></div>
                        </div>
                    </td>
                    <td>
                        <?php echo date('M d, Y', strtotime($app['application_date'])); ?>
                    </td>
                    <td>
                        <?php
                        $status_colors = [
                            'submitted' => '#3498db',
                            'in_review' => '#f39c12',
                            'shortlisted' => '#27ae60',
                            'interview_scheduled' => '#9b59b6',
                            'hired' => '#2ecc71',
                            'rejected' => '#e74c3c'
                        ];
                        $color = $status_colors[$app['status']] ?? '#7f8c8d';
                        ?>
                        <span class="category-badge" style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                            <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <button class="table-action" onclick="viewApplication(<?php echo htmlspecialchars(json_encode($app)); ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if ($app['resume_path']): ?>
                            <a href="<?php echo htmlspecialchars($app['resume_path']); ?>" target="_blank" class="table-action">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Application Details Modal -->
<div class="modal" id="appDetailsModal">
    <div class="modal-content" style="max-width: 700px;">
        <button class="modal-close" onclick="hideAppDetails()">
            <i class="fas fa-times"></i>
        </button>
        <h2 id="modalAppName">Application Details</h2>
        
        <div style="margin-top: 20px;" id="appDetailsContent"></div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <select id="appStatusSelect" style="flex: 2; padding: 12px; border-radius: 16px; border: 1px solid rgba(14,76,146,0.1);">
                <option value="submitted">Submitted</option>
                <option value="in_review">In Review</option>
                <option value="shortlisted">Shortlisted</option>
                <option value="interview_scheduled">Interview Scheduled</option>
                <option value="hired">Hired</option>
                <option value="rejected">Rejected</option>
            </select>
            <button class="add-expense-btn" onclick="updateStatus()" style="flex: 1;">
                <i class="fas fa-save"></i> Update
            </button>
        </div>
    </div>
</div>

<script>
let currentApp = null;

function viewApplication(app) {
    currentApp = app;
    
    document.getElementById('modalAppName').textContent = app.first_name + ' ' + app.last_name;
    document.getElementById('appStatusSelect').value = app.status;
    
    const html = `
        <div style="display: grid; gap: 20px;">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                <div>
                    <p style="font-size: 11px; color: #7f8c8d;">Application Number</p>
                    <p style="font-weight: 600;">${app.application_number}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: #7f8c8d;">Applied Date</p>
                    <p>${new Date(app.application_date).toLocaleDateString()}</p>
                </div>
            </div>
            
            <div style="background: #f8f9fa; border-radius: 16px; padding: 15px;">
                <h3 style="font-size: 14px; margin-bottom: 10px;">Contact Information</h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <div>
                        <p style="font-size: 11px; color: #7f8c8d;">Email</p>
                        <p>${app.email}</p>
                    </div>
                    <div>
                        <p style="font-size: 11px; color: #7f8c8d;">Phone</p>
                        <p>${app.phone}</p>
                    </div>
                    <div>
                        <p style="font-size: 11px; color: #7f8c8d;">Birth Date</p>
                        <p>${app.birth_date}</p>
                    </div>
                    <div>
                        <p style="font-size: 11px; color: #7f8c8d;">Gender</p>
                        <p>${app.gender}</p>
                    </div>
                </div>
                <div style="margin-top: 10px;">
                    <p style="font-size: 11px; color: #7f8c8d;">Address</p>
                    <p>${app.address || 'Not provided'}</p>
                </div>
            </div>
            
            ${app.education ? `
            <div>
                <h3 style="font-size: 14px; margin-bottom: 5px;">Education</h3>
                <p style="color: #4a5568;">${app.education.replace(/\n/g, '<br>')}</p>
            </div>
            ` : ''}
            
            ${app.work_experience ? `
            <div>
                <h3 style="font-size: 14px; margin-bottom: 5px;">Work Experience</h3>
                <p style="color: #4a5568;">${app.work_experience.replace(/\n/g, '<br>')}</p>
            </div>
            ` : ''}
            
            ${app.skills ? `
            <div>
                <h3 style="font-size: 14px; margin-bottom: 5px;">Skills</h3>
                <p style="color: #4a5568;">${app.skills.replace(/\n/g, '<br>')}</p>
            </div>
            ` : ''}
            
            ${app.certifications ? `
            <div>
                <h3 style="font-size: 14px; margin-bottom: 5px;">Certifications</h3>
                <p style="color: #4a5568;">${app.certifications.replace(/\n/g, '<br>')}</p>
            </div>
            ` : ''}
            
            ${app.references_info ? `
            <div>
                <h3 style="font-size: 14px; margin-bottom: 5px;">References</h3>
                <p style="color: #4a5568;">${app.references_info.replace(/\n/g, '<br>')}</p>
            </div>
            ` : ''}
            
            ${app.interview_notes ? `
            <div>
                <h3 style="font-size: 14px; margin-bottom: 5px;">Interview Notes</h3>
                <p style="color: #4a5568;">${app.interview_notes}</p>
            </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('appDetailsContent').innerHTML = html;
    document.getElementById('appDetailsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function hideAppDetails() {
    document.getElementById('appDetailsModal').classList.remove('active');
    document.body.style.overflow = '';
}

function updateStatus() {
    if (!currentApp) return;
    
    const newStatus = document.getElementById('appStatusSelect').value;
    
    // You would typically make an AJAX call here
    // For now, just show a message
    alert('Status update functionality would save: ' + newStatus);
    
    // In production, you'd do:
    /*
    fetch('ajax/update_application_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: currentApp.id, status: newStatus })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
    */
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('appDetailsModal');
    if (event.target == modal) {
        hideAppDetails();
    }
}
</script>