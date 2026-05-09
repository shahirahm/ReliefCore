
/**
 * Volunteer Dashboard Interactivity
 */

// Section Switching Logic
function showSection(sectionId, element) {
    const activeSection = document.getElementById(sectionId);
    if (!activeSection) return;

    // Check if already active to prevent redundant animations
    if (activeSection.style.display === 'block') return;

    // Hide all sections
    const sections = document.querySelectorAll('.content-section');
    sections.forEach(section => {
        section.style.display = 'none';
    });

    // Show the targeted section
    activeSection.style.display = 'block';
    
    // Reset main content scroll position smoothly
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.scrollTop = 0;
    }

    // Update active state in sidebar
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.classList.remove('active');
    });
    
    if (element) {
        element.classList.add('active');
    }
}

// Mark Task as Complete
function markComplete(btn) {
    const taskItem = btn.parentElement;
    const taskInfo = taskItem.querySelector('.task-info');
    
    // Create status badge
    const badge = document.createElement('span');
    badge.className = 'status-badge status-completed';
    badge.innerText = 'Completed';
    
    // Replace button with badge
    btn.remove();
    taskItem.appendChild(badge);
    
    // Visual feedback
    taskItem.style.opacity = '0.7';
    taskItem.style.background = 'var(--surface)';
}

// Chat Functionality
function sendMessage() {
    const input = document.getElementById('chatInput');
    const container = document.getElementById('chatMessages');
    
    if (input.value.trim() === '') return;
    
    // Create message element
    const msgDiv = document.createElement('div');
    msgDiv.className = 'message sent';
    msgDiv.innerText = input.value;
    
    // Add to container
    container.appendChild(msgDiv);
    
    // Scroll to bottom
    container.scrollTop = container.scrollHeight;
    
    // Clear input
    input.value = '';
    
    // Mock response from manager
    setTimeout(() => {
        const responseDiv = document.createElement('div');
        responseDiv.className = 'message received';
        responseDiv.innerText = "Received. I'll check that immediately.";
        container.appendChild(responseDiv);
        container.scrollTop = container.scrollHeight;
    }, 1500);
}

// Handle Hash and Initial Load
document.addEventListener('DOMContentLoaded', () => {
    // Check for hash in URL (e.g., #tasks)
    const hash = window.location.hash.substring(1);
    if (hash) {
        const navItem = document.querySelector(`.nav-item[onclick*="'${hash}'"]`);
        if (navItem) {
            showSection(hash, navItem);
        }
    }

    // Chat Enter key listener
    const chatInput = document.getElementById('chatInput');
    if (chatInput) {
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    }
});

// Handle browser back/forward buttons
window.addEventListener('hashchange', () => {
    const hash = window.location.hash.substring(1);
    if (hash) {
        const navItem = document.querySelector(`.nav-item[onclick*="'${hash}'"]`);
        if (navItem) {
            showSection(hash, navItem);
        }
    }
});

// Mock Form Submission Feedback
const forms = document.querySelectorAll('button');
forms.forEach(btn => {
    if (btn.innerText === 'Log Distribution' || btn.innerText === 'Submit Urgent Report') {
        btn.addEventListener('click', () => {
            const originalText = btn.innerText;
            btn.innerText = 'Success!';
            btn.style.background = 'var(--emerald)';
            btn.style.color = '#0a0e1a';
            
            setTimeout(() => {
                btn.innerText = originalText;
                btn.style.background = '';
                btn.style.color = '';
                // Clear inputs
                const inputs = btn.parentElement.querySelectorAll('input, textarea');
                inputs.forEach(i => i.value = '');
            }, 2000);
        });
    }
});
