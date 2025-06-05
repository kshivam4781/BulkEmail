<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Check if list ID is provided
if (!isset($_GET['id'])) {
    header('Location: email_lists.php');
    exit();
}

try {
    $db = (new Database())->connect();
    
    // Get list details
    $stmt = $db->prepare("
        SELECT 
            cl.*,
            u.email as creator_email
        FROM contactlists cl
        LEFT JOIN users u ON cl.UserID = u.userId
        WHERE cl.ListID = ?
    ");
    $stmt->execute([$_GET['id']]);
    $list = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$list) {
        header('Location: email_lists.php');
        exit();
    }
    
    // Get contacts
    $stmt = $db->prepare("
        SELECT * FROM contacts 
        WHERE ListID = ? 
        ORDER BY FullName
    ");
    $stmt->execute([$_GET['id']]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    header('Location: email_lists.php');
    exit();
}

// Prepare the content for the layout
ob_start();
?>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Edit List: <?php echo htmlspecialchars($list['Name']); ?></h4>
                    <p class="text-muted mb-0">
                        Created by <?php echo htmlspecialchars($list['creator_email']); ?> on 
                        <?php echo date('M d, Y H:i', strtotime($list['CreatedAt'])); ?>
                    </p>
                </div>
                <div>
                    <a href="email_lists.php" class="btn btn-light me-2">
                        <i class="fas fa-arrow-left me-2"></i>Back to Lists
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Form Section -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form id="editListForm">
                        <input type="hidden" id="listId" value="<?php echo $list['ListID']; ?>">
                        
                        <!-- List Name -->
                        <div class="mb-4">
                            <label for="listName" class="form-label fw-bold">List Name</label>
                            <input type="text" class="form-control" id="listName" value="<?php echo htmlspecialchars($list['Name']); ?>" required>
                        </div>

                        <!-- Add New Contact -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Add New Contact</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="contactName" placeholder="Full Name">
                                <input type="email" class="form-control" id="contactEmail" placeholder="Email">
                                <button type="button" class="btn btn-outline-primary" onclick="addContact()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Contacts List -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Contacts</label>
                            <div id="contactsList" class="mb-3">
                                <?php foreach ($contacts as $contact): ?>
                                    <div class="alert alert-light d-flex justify-content-between align-items-center mb-2" data-contact-id="<?php echo $contact['ContactID']; ?>">
                                        <div class="d-flex align-items-center flex-grow-1">
                                            <input type="text" class="form-control me-2" value="<?php echo htmlspecialchars($contact['FullName']); ?>" onchange="updateContact(<?php echo $contact['ContactID']; ?>, 'name', this.value)">
                                            <input type="email" class="form-control me-2" value="<?php echo htmlspecialchars($contact['Email']); ?>" onchange="updateContact(<?php echo $contact['ContactID']; ?>, 'email', this.value)">
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteContact(<?php echo $contact['ContactID']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary" onclick="saveList()">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Store new contacts temporarily
let newContacts = [];

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
    
    // Add to new contacts array
    newContacts.push({ name, email });
    
    // Add to display
    const container = document.getElementById('contactsList');
    const div = document.createElement('div');
    div.className = 'alert alert-light d-flex justify-content-between align-items-center mb-2';
    div.innerHTML = `
        <div class="d-flex align-items-center flex-grow-1">
            <input type="text" class="form-control me-2" value="${name}" readonly>
            <input type="email" class="form-control me-2" value="${email}" readonly>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeNewContact(${newContacts.length - 1})">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(div);
    
    // Clear inputs
    document.getElementById('contactName').value = '';
    document.getElementById('contactEmail').value = '';
}

function removeNewContact(index) {
    newContacts.splice(index, 1);
    updateContactsList();
}

function updateContactsList() {
    const container = document.getElementById('contactsList');
    container.innerHTML = '';
    
    // Add existing contacts
    <?php foreach ($contacts as $contact): ?>
        const div = document.createElement('div');
        div.className = 'alert alert-light d-flex justify-content-between align-items-center mb-2';
        div.setAttribute('data-contact-id', '<?php echo $contact['ContactID']; ?>');
        div.innerHTML = `
            <div class="d-flex align-items-center flex-grow-1">
                <input type="text" class="form-control me-2" value="<?php echo htmlspecialchars($contact['FullName']); ?>" onchange="updateContact(<?php echo $contact['ContactID']; ?>, 'name', this.value)">
                <input type="email" class="form-control me-2" value="<?php echo htmlspecialchars($contact['Email']); ?>" onchange="updateContact(<?php echo $contact['ContactID']; ?>, 'email', this.value)">
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteContact(<?php echo $contact['ContactID']; ?>)">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(div);
    <?php endforeach; ?>
    
    // Add new contacts
    newContacts.forEach((contact, index) => {
        const div = document.createElement('div');
        div.className = 'alert alert-light d-flex justify-content-between align-items-center mb-2';
        div.innerHTML = `
            <div class="d-flex align-items-center flex-grow-1">
                <input type="text" class="form-control me-2" value="${contact.name}" readonly>
                <input type="email" class="form-control me-2" value="${contact.email}" readonly>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeNewContact(${index})">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(div);
    });
}

function updateContact(contactId, field, value) {
    fetch('update_contact.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            contactId: contactId,
            field: field,
            value: value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Error updating contact: ' + data.message);
            updateContactsList(); // Refresh to show original values
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating contact');
        updateContactsList(); // Refresh to show original values
    });
}

function deleteContact(contactId) {
    if (confirm('Are you sure you want to delete this contact?')) {
        fetch('delete_contact.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ contactId: contactId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateContactsList();
            } else {
                alert('Error deleting contact: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting contact');
        });
    }
}

function saveList() {
    const listId = document.getElementById('listId').value;
    const name = document.getElementById('listName').value.trim();
    
    if (!name) {
        alert('Please enter a list name');
        return;
    }
    
    // Create form data
    const formData = new FormData();
    formData.append('listId', listId);
    formData.append('name', name);
    formData.append('contacts', JSON.stringify(newContacts));
    
    // Send request
    fetch('update_list.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.href = 'email_lists.php';
        } else {
            alert('Error updating list: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating list');
    });
}
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?> 