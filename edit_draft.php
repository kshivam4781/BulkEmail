<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get draft ID from URL
$draftId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$draftId) {
    header('Location: email_drafts.php');
    exit();
}

// Get draft details
$db = (new Database())->connect();
$stmt = $db->prepare("
    SELECT * FROM emaildrafts 
    WHERE DraftID = ? AND UserID = ?
");
$stmt->execute([$draftId, $_SESSION['user_id']]);
$draft = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$draft) {
    header('Location: email_drafts.php');
    exit();
}

// Prepare the content for the layout
ob_start();
?>

<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Edit Email Draft</h4>
                <a href="email_drafts.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Drafts
                </a>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form id="draftForm" action="save_draft.php" method="POST">
                        <input type="hidden" name="draft_id" value="<?php echo $draft['DraftID']; ?>">
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" 
                                   value="<?php echo htmlspecialchars($draft['Subject']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="draftBody" class="form-label">Message Body</label>
                            <textarea id="draftBody" name="body" class="form-control"><?php echo htmlspecialchars($draft['Body']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="attachment" class="form-label">Attachment</label>
                            <?php if ($draft['Attachment']): ?>
                                <div class="mb-2">
                                    <i class="fas fa-paperclip me-2"></i>
                                    <?php echo htmlspecialchars($draft['Attachment']); ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removeAttachment()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="attachment" name="attachment">
                            <small class="text-muted">Leave empty to keep existing attachment</small>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light" onclick="window.location.href='email_drafts.php'">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="saveDraft()">Save Changes</button>
                        </div>
                    </form>
                </div>
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
            window.location.href = 'email_drafts.php';
        } else {
            alert('Error saving draft: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving draft');
    });
}

function removeAttachment() {
    if (confirm('Are you sure you want to remove the attachment?')) {
        fetch('remove_attachment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                draftId: <?php echo $draft['DraftID']; ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error removing attachment: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error removing attachment');
        });
    }
}
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 