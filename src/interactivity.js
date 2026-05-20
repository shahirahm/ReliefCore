/**
 * ReliefCore Foundation - Interactivity Simulation
 * This script adds functionality to all buttons, links, and chat interfaces.
 */

document.addEventListener('DOMContentLoaded', () => {
    initGlobalFeatures();
    initDashboardFeatures();
    initDonationFeatures();
    initDonorIDFeatures();
    initAdminSupportFeatures();
});

// --- GLOBAL FEATURES ---
function initGlobalFeatures() {
    // 1. "Become Monthly Hero" buttons
    const heroButtons = document.querySelectorAll('.hero-button, .hero-btn');
    heroButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            showNotification('Thank you! You are now a Monthly Relief Hero. ❤️');
        });
    });

    // 2. Chat Icons (FABs) - Redirect to Admin Support
    const chatIcons = document.querySelectorAll('.chat-icon-container, .chat-fab, .chat-box');
    chatIcons.forEach(icon => {
        icon.style.cursor = 'pointer';
        icon.addEventListener('click', (e) => {
            // Prevent interference if clicking inside the chat box on admin page
            if (window.location.pathname.includes('admin1.html')) return;
            
            showNotification('Connecting to Secure Support Node...');
            setTimeout(() => {
                window.location.href = 'admin1.html';
            }, 1000);
        });
    });

    // 3. Logout / Sign Out
    const signOutLinks = document.querySelectorAll('.sign-out-link, .sign-out, .logout-link');
    signOutLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            if (confirm('Are you sure you want to securely sign out?')) {
                showNotification('Signing out securely...');
                setTimeout(() => {
                    alert('You have been signed out. (Simulation)');
                }, 1000);
            }
        });
    });
}

// --- DASHBOARD PAGE ---
function initDashboardFeatures() {
    if (!document.querySelector('.main-content')) return;

    // "Initiate New Relief" -> Donation Page
    const initiateBtn = document.querySelector('.gold-pill-btn');
    if (initiateBtn) {
        initiateBtn.style.cursor = 'pointer';
        initiateBtn.addEventListener('click', () => {
            window.location.href = 'donation.html';
        });
    }

    // "Join Campaign" -> Success
    const joinBtn = document.querySelector('.outline-btn');
    if (joinBtn) {
        joinBtn.style.cursor = 'pointer';
        joinBtn.addEventListener('click', () => {
            joinBtn.innerText = '✓ JOINED';
            joinBtn.style.borderColor = 'var(--green-status, #00ff9d)';
            joinBtn.style.color = 'var(--green-status, #00ff9d)';
            showNotification('Welcome to the South Asia Monsoon Crisis relief team!');
        });
    }

    // "View All Records" / "Real-Time Map"
    const footerLinks = document.querySelectorAll('.footer-link');
    footerLinks.forEach(link => {
        link.style.cursor = 'pointer';
        link.addEventListener('click', () => {
            if (link.innerText.includes('RECORDS')) {
                window.location.href = 'donation.html';
            } else {
                showNotification('Opening Global Logistics Map...');
            }
        });
    });
}

// --- DONATION PAGE ---
function initDonationFeatures() {
    const donationGrid = document.querySelector('.donation-grid');
    if (!donationGrid) return;

    const toggles = document.querySelectorAll('.toggle-switch span');
    const presetContainer = document.querySelector('.amount-presets');
    const amountInput = document.querySelector('.amount-input');

    const moneyPresets = ['$50', '$100', '$500', 'CUSTOM'];
    const supplyPresets = ['FOOD', 'CLOTHS', 'DRY FOOD', 'CUSTOM'];

    function updatePresets(isMoney) {
        const items = isMoney ? moneyPresets : supplyPresets;
        presetContainer.innerHTML = '';
        
        items.forEach(text => {
            const div = document.createElement('div');
            div.className = 'preset-box';
            div.innerText = text;
            div.style.cursor = 'pointer';
            
            div.addEventListener('click', () => {
                document.querySelectorAll('.preset-box').forEach(p => p.style.borderColor = '#222');
                div.style.borderColor = 'var(--gold)';
                
                const label = isMoney ? '<span>$</span>' : '<span>ITEM:</span>';
                if (text !== 'CUSTOM') {
                    amountInput.innerHTML = `${label} ${text.replace('$', '')}${isMoney ? '.00' : ''}`;
                } else {
                    amountInput.innerHTML = `${label} <input type="text" placeholder="${isMoney ? 'Enter amount' : 'Enter item/qty'}" style="background:transparent; border:none; color:white; font-size:16px; outline:none; width:150px;">`;
                }
            });
            presetContainer.appendChild(div);
        });

        // Reset input label
        amountInput.innerHTML = isMoney ? '<span>$</span> SPECIFIC AMOUNT' : '<span>ITEM:</span> SPECIFIC QUANTITY';
    }

    // Toggle Money/Supplies
    toggles.forEach((span, index) => {
        span.addEventListener('click', () => {
            toggles.forEach(s => s.classList.remove('active'));
            span.classList.add('active');
            
            const isMoney = span.innerText === 'MONEY';
            updatePresets(isMoney);
            showNotification(`Mode switched to: ${span.innerText}`);
        });
    });

    // Initial load
    updatePresets(true);

    // Finalize Transaction
    const finalizeBtn = document.querySelector('.finalize-btn');
    if (finalizeBtn) {
        finalizeBtn.addEventListener('click', () => {
            const isMoney = document.querySelector('.toggle-switch span.active').innerText === 'MONEY';
            finalizeBtn.innerText = 'ENCRYPTING...';
            finalizeBtn.disabled = true;
            setTimeout(() => {
                finalizeBtn.innerText = '✓ TRANSACTION SECURED';
                showNotification(isMoney ? 'Transaction successful! Funds have been allocated.' : 'Donation logged! Logistics node notified for collection.');
            }, 2000);
        });
    }

    // Downloads
    const downloadIcons = document.querySelectorAll('.download-icon, .discover, .vault-btn');
    downloadIcons.forEach(item => {
        item.style.cursor = 'pointer';
        item.addEventListener('click', () => {
            simulateDownload('ReliefCore_Transaction_Receipt.pdf');
        });
    });
}

// --- DONOR ID PAGE ---
function initDonorIDFeatures() {
    const idCard = document.querySelector('.identity-card');
    if (!idCard) return;

    // PDF Download
    const pdfBtn = document.querySelector('.btn-gold');
    if (pdfBtn) {
        pdfBtn.addEventListener('click', () => {
            showNotification('Generating Official Identity Extract...');
            setTimeout(() => {
                simulateDownload('ReliefCore_Donor_ID_Extract.pdf');
            }, 1500);
        });
    }

    // Distribute Credentials
    const distributeBtn = document.querySelector('.btn-outline');
    if (distributeBtn) {
        distributeBtn.addEventListener('click', () => {
            showNotification('Credentials securely transmitted to your registered email.');
        });
    }
}

// --- ADMIN SUPPORT PAGE ---
function initAdminSupportFeatures() {
    const chatContent = document.querySelector('.chat-content');
    const chatInput = document.querySelector('.input-container input');
    const sendBtn = document.querySelector('.send-btn');

    if (!chatInput || !sendBtn) return;

    function sendMessage() {
        const text = chatInput.value.trim();
        if (!text) return;

        // Append user message
        const userMsg = document.createElement('div');
        userMsg.className = 'message outgoing';
        userMsg.innerHTML = `<p>${text}</p>`;
        chatContent.appendChild(userMsg);
        
        chatInput.value = '';
        chatContent.scrollTop = chatContent.scrollHeight;

        // Simulate AI Response
        setTimeout(() => {
            const botMsg = document.createElement('div');
            botMsg.className = 'message incoming';
            botMsg.innerHTML = `<p>Processing request... Your query has been logged under ID #RG-8829. A support agent will verify this shortly.</p>`;
            chatContent.appendChild(botMsg);
            chatContent.scrollTop = chatContent.scrollHeight;
        }, 1000);
    }

    sendBtn.addEventListener('click', sendMessage);
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });
}

// --- UTILS ---
function showNotification(message) {
    const note = document.createElement('div');
    note.style.cssText = `
        position: fixed;
        bottom: 100px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(197, 163, 103, 0.95);
        color: #000;
        padding: 15px 30px;
        border-radius: 30px;
        font-weight: 800;
        font-size: 13px;
        z-index: 9999;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        border: 1px solid #fff;
        animation: slideUp 0.3s ease-out;
    `;
    note.innerText = message;
    document.body.appendChild(note);

    setTimeout(() => {
        note.style.opacity = '0';
        note.style.transition = '0.5s';
        setTimeout(() => note.remove(), 500);
    }, 3000);
}

function simulateDownload(filename) {
    showNotification(`Downloading: ${filename}`);
    const link = document.createElement('a');
    link.href = '#';
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Add animation keyframes via JS
const style = document.createElement('style');
style.innerHTML = `
    @keyframes slideUp {
        from { transform: translate(-50%, 50px); opacity: 0; }
        to { transform: translate(-50%, 0); opacity: 1; }
    }
`;
document.head.appendChild(style);
