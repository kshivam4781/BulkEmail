<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get all email lists with contact counts
$db = (new Database())->connect();
$stmt = $db->prepare("
    SELECT 
        cl.*,
        u.email as creator_email,
        COUNT(c.ContactID) as contact_count
    FROM contactlists cl
    LEFT JOIN users u ON cl.UserID = u.userId
    LEFT JOIN contacts c ON c.ListID = cl.ListID
    GROUP BY cl.ListID
    ORDER BY cl.CreatedAt DESC
");
$stmt->execute();
$lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare the content for the layout
ob_start();
?>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Email Lists</h4>
                    <p class="text-muted mb-0">Manage your email contact lists</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newListModal">
                    <i class="fas fa-plus me-2"></i>New List
                </button>
            </div>
        </div>
    </div>

    <!-- Lists Section -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($lists)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>No Email Lists Found</h5>
                            <p class="text-muted">Create your first email list to get started</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>List Name</th>
                                        <th>Created By</th>
                                        <th>Contacts</th>
                                        <th>Created Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lists as $list): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($list['Name']); ?></td>
                                            <td><?php echo htmlspecialchars($list['creator_email']); ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo $list['contact_count']; ?> contacts
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y H:i', strtotime($list['CreatedAt'])); ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="view_list.php?id=<?php echo $list['ListID']; ?>" class="btn btn-sm btn-light" title="View List">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="new_campaign.php?list=<?php echo $list['ListID']; ?>" class="btn btn-sm btn-primary" title="Use for Campaign">
                                                        <i class="fas fa-envelope"></i>
                                                    </a>
                                                    <a href="edit_list.php?id=<?php echo $list['ListID']; ?>" class="btn btn-sm btn-info" title="Edit List">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteList(<?php echo $list['ListID']; ?>)" title="Delete List">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
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

<!-- New List Modal -->
<div class="modal fade" id="newListModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title">Create New List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newListForm">
                    <div class="mb-3">
                        <label for="listName" class="form-label">List Name</label>
                        <input type="text" class="form-control" id="listName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Add Contacts</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="contactName" placeholder="Full Name">
                            <input type="email" class="form-control" id="contactEmail" placeholder="Email">
                            <button type="button" class="btn btn-outline-primary" onclick="addContact()">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div id="contactsList" class="mb-3">
                        <!-- Contacts will be added here -->
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveList()">Create List</button>
            </div>
        </div>
    </div>
</div>

<script>
// Store contacts temporarily
let contacts = [];

function addContact() {
    const name = document.getElementById('contactName').value.trim();
    const email = document.getElementById('contactEmail').value.trim();
    
    if (!name || !email) {
        alert('Please enter both name and email');
        return;
    }
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Please enter a valid email address');
        return;
    }
    
    // Add to contacts array
    contacts.push({ name, email });
    
    // Update display
    updateContactsList();
    
    // Clear inputs
    document.getElementById('contactName').value = '';
    document.getElementById('contactEmail').value = '';
}

function updateContactsList() {
    const container = document.getElementById('contactsList');
    container.innerHTML = '';
    
    contacts.forEach((contact, index) => {
        const div = document.createElement('div');
        div.className = 'alert alert-light d-flex justify-content-between align-items-center mb-2';
        div.innerHTML = `
            <div>
                <strong>${contact.name}</strong><br>
                <small class="text-muted">${contact.email}</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeContact(${index})">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(div);
    });
}

function removeContact(index) {
    contacts.splice(index, 1);
    updateContactsList();
}

function saveList() {
    const name = document.getElementById('listName').value.trim();
    
    if (!name) {
        alert('Please enter a list name');
        return;
    }
    
    if (contacts.length === 0) {
        alert('Please add at least one contact');
        return;
    }
    
    // Create form data
    const formData = new FormData();
    formData.append('name', name);
    formData.append('contacts', JSON.stringify(contacts));
    
    // Send request
    fetch('save_list.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error creating list: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating list');
    });
}

function viewList(listId) {
    window.location.href = `view_list.php?id=${listId}`;
}

function useList(listId) {
    window.location.href = `new_campaign.php?list=${listId}`;
}

function deleteList(listId) {
    if (confirm('Are you sure you want to delete this list?')) {
        fetch('delete_list.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ listId: listId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting list: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting list');
        });
    }
}
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 