<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get user's drafts
$db = (new Database())->connect();
$stmt = $db->prepare("
    SELECT * FROM emaildrafts 
    WHERE UserID = ? 
    ORDER BY LastModified DESC
");
$stmt->execute([$_SESSION['user_id']]);
$drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare the content for the layout
ob_start();
?>

<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Email Drafts</h4>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newDraftModal">
                    <i class="fas fa-plus me-2"></i>New Draft
                </button>
            </div>
        </div>
    </div>

    <!-- Drafts List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($drafts)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <h5>No Drafts Found</h5>
                            <p class="text-muted">Create your first email draft to get started</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Last Modified</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($drafts as $draft): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($draft['Subject']); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($draft['LastModified'])); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($draft['CreatedAt'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary me-2" 
                                                        onclick="editDraft(<?php echo $draft['DraftID']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-success me-2" 
                                                        onclick="useDraft(<?php echo $draft['DraftID']; ?>)">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteDraft(<?php echo $draft['DraftID']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Draft Modal -->
<div class="modal fade" id="newDraftModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title">New Email Draft</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="draftForm" action="save_draft.php" method="POST">
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label for="draftBody" class="form-label">Message Body</label>
                        <textarea id="draftBody" name="body" class="form-control"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="attachment" class="form-label">Attachment (Optional)</label>
                        <input type="file" class="form-control" id="attachment" name="attachment">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveDraft()">Save Draft</button>
            </div>
        </div>
    </div>
</div>

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/ty9olnipt398xv19du58z8ofchfkuctsjo8mwcwqry4jmwfy/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize TinyMCE
    tinymce.init({
        selector: '#draftBody',
        height: 400,
        menubar: true,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons',
            'template', 'codesample', 'hr', 'pagebreak', 'nonbreaking', 'toc',
            'visualchars', 'quickbars', 'emoticons'
        ],
        toolbar: 'undo redo | blocks | ' +
            'bold italic backcolor | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'removeformat | help | image media table emoticons | ' +
            'forecolor backcolor | fontfamily fontsize | ' +
            'link anchor codesample | charmap | preview fullscreen',
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 14px; }',
        templates: [
            { title: 'New Table', description: 'creates a new table', content: '<div class="mceTmpl"><table width="98%%"  border="0" cellspacing="0" cellpadding="0"><tr><th scope="col"> </th><th scope="col"> </th></tr><tr><td> </td><td> </td></tr></table></div>' },
            { title: 'Starting my story', description: 'A cure for writers block', content: 'Once upon a time...' },
            { title: 'New list with dates', description: 'New List with dates', content: '<div class="mceTmpl"><span class="cdate">cdate</span><br><span class="mdate">mdate</span><h2>My List</h2><ul><li></li><li></li></ul></div>' }
        ]
    });
});

function saveDraft() {
    const form = document.getElementById('draftForm');
    const formData = new FormData(form);
    
    // Add TinyMCE content
    formData.set('body', tinymce.get('draftBody').getContent());
    
    fetch('save_draft.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error saving draft: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving draft');
    });
}

function editDraft(draftId) {
    window.location.href = `edit_draft.php?id=${draftId}`;
}

function useDraft(draftId) {
    window.location.href = `new_campaign.php?draft=${draftId}`;
}

function deleteDraft(draftId) {
    if (confirm('Are you sure you want to delete this draft?')) {
        fetch('delete_draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ draftId: draftId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting draft: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting draft');
        });
    }
}
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 