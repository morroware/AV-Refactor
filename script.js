const x7y9z3 = "1313";
const passwordDialog = document.getElementById('passwordDialog');
const container = document.querySelector('.container');
const passwordInput = document.getElementById('passwordInput');
const submitPassword = document.getElementById('submitPassword');
const errorMessage = document.getElementById('errorMessage');
const attemptCounter = document.getElementById('attemptCounter');
const togglePassword = document.getElementById('togglePassword');
const logoElements = document.querySelectorAll('.logo');

// Storage adapter - uses LiveCode compatibility layer if available, falls back to localStorage
const storage = (window.LiveCodeCompat && window.LiveCodeCompat.storage) || {
    getItem: function(key) { try { return localStorage.getItem(key); } catch(e) { return null; } },
    setItem: function(key, value) { try { localStorage.setItem(key, value); } catch(e) {} },
    removeItem: function(key) { try { localStorage.removeItem(key); } catch(e) {} }
};

let attemptCount = 0;
const maxAttempts = 10;
let isCtrlPressed = false;
let lastClickTime = 0;
const doubleClickDelay = 300; // milliseconds

// Password visibility toggle
if (togglePassword) {
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        // Update icon and aria-label
        const eyeIcon = document.getElementById('eyeIcon');
        if (type === 'text') {
            this.setAttribute('aria-label', 'Hide password');
            eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
        } else {
            this.setAttribute('aria-label', 'Show password');
            eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
        }
    });
}

function updateAttemptCounter() {
    if (attemptCounter && attemptCount > 0 && attemptCount < maxAttempts) {
        attemptCounter.textContent = `${maxAttempts - attemptCount} attempts remaining`;
    } else if (attemptCounter) {
        attemptCounter.textContent = '';
    }
}

function checkPassword() {
    if (passwordInput.value === x7y9z3) {
        // Show success feedback before transitioning
        errorMessage.style.color = '#00C853';
        errorMessage.textContent = 'Access granted!';
        submitPassword.disabled = true;

        setTimeout(() => {
            setAuthenticated();
            showContent();
            errorMessage.textContent = '';
            errorMessage.style.color = '';
        }, 500);
    } else {
        attemptCount++;
        if (attemptCount >= maxAttempts) {
            errorMessage.textContent = 'Too many failed attempts. Please refresh the page to try again.';
            passwordInput.disabled = true;
            submitPassword.disabled = true;
            if (attemptCounter) attemptCounter.textContent = '';
        } else {
            errorMessage.textContent = 'Incorrect password. Please try again.';
            passwordInput.value = '';
            passwordInput.focus();
            updateAttemptCounter();

            // Add shake animation
            passwordInput.classList.add('shake');
            setTimeout(() => passwordInput.classList.remove('shake'), 500);
        }
    }
}

function setAuthenticated() {
    const now = new Date();
    const endOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59, 999);
    storage.setItem('authExpiration', endOfDay.getTime().toString());
}

function isAuthenticated() {
    const authExpiration = storage.getItem('authExpiration');
    if (authExpiration) {
        if (Date.now() < parseInt(authExpiration)) {
            return true;
        } else {
            storage.removeItem('authExpiration');
        }
    }
    return false;
}

function showContent() {
    passwordDialog.style.display = 'none';
    container.style.display = 'block';
}

function init() {
    if (isAuthenticated()) {
        showContent();
    } else {
        passwordDialog.style.display = 'flex';
        container.style.display = 'none';

        // Focus password input after a brief delay
        setTimeout(function() {
            if (passwordInput) {
                passwordInput.focus();
            }
        }, 100);
    }

    // Set up event listeners for the logo control+double-click functionality
    setupLogoControlDoubleClick();
}

// On-screen keypad for LiveCode browser widget (keyboard fallback)
function createOnScreenKeypad() {
    // Check if already exists
    if (document.getElementById('lc-keypad')) return;

    var keypad = document.createElement('div');
    keypad.id = 'lc-keypad';
    keypad.innerHTML = `
        <style>
            #lc-keypad {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
                max-width: 240px;
                margin: 1rem auto 0;
                padding: 1rem;
                background: rgba(0,0,0,0.2);
                border-radius: 12px;
            }
            #lc-keypad button {
                padding: 1rem;
                font-size: 1.25rem;
                font-weight: 600;
                border: 1px solid rgba(255,255,255,0.1);
                border-radius: 8px;
                background: rgba(99, 102, 241, 0.3);
                color: #fff;
                cursor: pointer;
                transition: all 0.15s;
            }
            #lc-keypad button:hover {
                background: rgba(99, 102, 241, 0.5);
            }
            #lc-keypad button:active {
                transform: scale(0.95);
            }
            #lc-keypad .wide {
                grid-column: span 1;
            }
            #lc-keypad .backspace {
                background: rgba(239, 68, 68, 0.3);
            }
            #lc-keypad .enter {
                background: rgba(34, 197, 94, 0.4);
            }
        </style>
        <button type="button" onclick="keypadPress('1')">1</button>
        <button type="button" onclick="keypadPress('2')">2</button>
        <button type="button" onclick="keypadPress('3')">3</button>
        <button type="button" onclick="keypadPress('4')">4</button>
        <button type="button" onclick="keypadPress('5')">5</button>
        <button type="button" onclick="keypadPress('6')">6</button>
        <button type="button" onclick="keypadPress('7')">7</button>
        <button type="button" onclick="keypadPress('8')">8</button>
        <button type="button" onclick="keypadPress('9')">9</button>
        <button type="button" class="backspace" onclick="keypadPress('backspace')">⌫</button>
        <button type="button" onclick="keypadPress('0')">0</button>
        <button type="button" class="enter" onclick="keypadPress('enter')">↵</button>
    `;

    // Insert after password form
    var form = document.getElementById('passwordForm');
    if (form) {
        form.appendChild(keypad);
    }
}

// Handle keypad button press
window.keypadPress = function(key) {
    if (!passwordInput) return;

    if (key === 'backspace') {
        passwordInput.value = passwordInput.value.slice(0, -1);
    } else if (key === 'enter') {
        checkPassword();
    } else {
        passwordInput.value += key;
    }
    passwordInput.focus();
};

// Track Control key state
document.addEventListener('keydown', function(event) {
    if (event.key === 'Control') {
        isCtrlPressed = true;
    }
});

document.addEventListener('keyup', function(event) {
    if (event.key === 'Control') {
        isCtrlPressed = false;
    }
});

// Setup logo control+double-click functionality
function setupLogoControlDoubleClick() {
    logoElements.forEach(logo => {
        logo.addEventListener('click', function(event) {
            const currentTime = new Date().getTime();
            
            if (isCtrlPressed) {
                // Check if this is a double click (two clicks within doubleClickDelay ms)
                if (currentTime - lastClickTime < doubleClickDelay) {
                    // This is a control+double-click - redirect to home page
                    window.location.href = 'index.html';
                }
                
                // Update the last click time
                lastClickTime = currentTime;
            }
        });
    });
}

submitPassword.addEventListener('click', checkPassword);

passwordInput.addEventListener('keyup', function(event) {
    if (event.key === 'Enter') {
        checkPassword();
    }
});

// Also handle keydown for Enter (some browsers/widgets prefer this)
passwordInput.addEventListener('keydown', function(event) {
    if (event.key === 'Enter' || event.keyCode === 13) {
        event.preventDefault();
        checkPassword();
    }
});

// LiveCode can call this function directly to set password value
// Usage from LiveCode: do "setPasswordValue('1234')" in widget "browser"
window.setPasswordValue = function(value) {
    if (passwordInput) {
        passwordInput.value = value;
        passwordInput.focus();
    }
};

// LiveCode can call this to submit the password
// Usage from LiveCode: do "submitPasswordFromLiveCode()" in widget "browser"
window.submitPasswordFromLiveCode = function() {
    checkPassword();
};

document.querySelectorAll('.button').forEach(button => {
    button.addEventListener('click', function(event) {
        // Only intercept if this button doesn't have an href (dynamically generated ones do)
        if (!this.href || this.href === window.location.href) {
            event.preventDefault();
            const buttonName = this.textContent.toLowerCase().replace(' ', '');
            const newUrl = buttonName + '/';
            window.location.href = newUrl;
        }
        // Let buttons with proper hrefs navigate normally
    });
});

// Initialize the application
init();
