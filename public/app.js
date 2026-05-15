/**
 * Mushaf Task Manager with Token Auth
 */

const API_BASE_URL = 'http://mushaf.linuxproguru.com/';
let currentUser = null;
let authToken = localStorage.getItem('mushaf_token') || null;
let savedPage = '';
let savedRiwayah = '';

// DOM Elements
const loginScreen = document.getElementById('loginScreen');
const mainApp = document.getElementById('mainApp');
const loginForm = document.getElementById('loginForm');
const loginError = document.getElementById('loginError');
const userDisplay = document.getElementById('userDisplay');
const logsModal = document.getElementById('logsModal');
const logsContainer = document.getElementById('logsContainer');

const form = document.getElementById('reviewForm');
const successMessage = document.getElementById('successMessage');
const tasksContainer = document.getElementById('tasksContainer');
const taskTemplate = document.getElementById('taskTemplate');
const pageNumberInput = document.getElementById('pageNumber');
const juzDisplay = document.getElementById('juzDisplay');
const riwayahSelect = document.getElementById('riwayah');
const lastLocationDiv = document.getElementById('lastLocation');
const suggestionsBox = document.getElementById('suggestionsBox');

let taskCount = 0;

const pageToJuz = {
    1: [1, 21], 2: [22, 41], 3: [42, 61], 4: [62, 81],
    5: [82, 101], 6: [102, 121], 7: [122, 141], 8: [142, 161],
    9: [162, 181], 10: [182, 201], 11: [202, 221], 12: [222, 241],
    13: [242, 261], 14: [262, 281], 15: [282, 301], 16: [302, 321],
    17: [322, 341], 18: [342, 361], 19: [362, 381], 20: [382, 401],
    21: [402, 421], 22: [422, 441], 23: [442, 461], 24: [462, 481],
    25: [482, 501], 26: [502, 521], 27: [522, 541], 28: [542, 561],
    29: [562, 581], 30: [582, 604]
};

function getJuzFromPage(page) {
    for (let juz = 1; juz <= 30; juz++) {
        const [start, end] = pageToJuz[juz];
        if (page >= start && page <= end) return juz;
    }
    return null;
}

// Helper for API calls
async function apiCall(endpoint, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            ...(authToken ? {'X-Auth-Token': authToken} : {})
        }
    };
    
    const res = await fetch(API_BASE_URL + endpoint, {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...(options.headers || {})
        }
    });
    
    return res;
}

// ==================== AUTH ====================
async function checkAuth() {
    if (!authToken) {
        showLogin();
        return;
    }
    
    try {
        const res = await apiCall('check-auth.php');
        const data = await res.json();
        
        if (data.loggedIn) {
            currentUser = data.user;
            showMainApp(data.lastLocation);
        } else {
            // Token invalid
            authToken = null;
            localStorage.removeItem('mushaf_token');
            showLogin();
        }
    } catch (err) {
        console.error('Auth check failed:', err);
        showLogin();
    }
}

function showLogin() {
    loginScreen.classList.remove('hidden');
    mainApp.classList.add('hidden');
}

function showMainApp(lastLocation) {
    loginScreen.classList.add('hidden');
    mainApp.classList.remove('hidden');
    userDisplay.textContent = 'Welcome, ' + capitalize(currentUser);
    
    initApp(lastLocation);
}

function capitalize(s) {
    return s.charAt(0).toUpperCase() + s.slice(1);
}

loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    
    try {
        const res = await fetch(API_BASE_URL + 'login.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({username, password})
        });
        const data = await res.json();
        
        if (data.success) {
            currentUser = data.user;
            authToken = data.token;
            localStorage.setItem('mushaf_token', authToken);
            showMainApp(data.lastLocation);
        } else {
            loginError.classList.remove('hidden');
        }
    } catch (err) {
        console.error('Login error:', err);
        loginError.classList.remove('hidden');
    }
});

document.getElementById('logoutBtn').addEventListener('click', () => {
    authToken = null;
    localStorage.removeItem('mushaf_token');
    location.reload();
});

// ==================== APP INIT ====================
function initApp(lastLocation) {
    populateRiwayahOptions();
    addTask();
    setupEventListeners();
    
    if (lastLocation && lastLocation.page) {
        document.getElementById('lastPage').textContent = lastLocation.page;
        document.getElementById('lastRiwayah').textContent = lastLocation.riwayah;
        lastLocationDiv.classList.remove('hidden');
        
        document.getElementById('useLastLocation').addEventListener('click', () => {
            pageNumberInput.value = lastLocation.page;
            riwayahSelect.value = lastLocation.riwayah;
            handlePageInput();
            loadSuggestions(lastLocation.riwayah);
        });
    }
}

// ==================== RIWAYAH & SUGGESTIONS ====================
function populateRiwayahOptions() {
    const common = ['Warsh', 'Hafs', 'Qaloon', 'Al-Duri', 'Al-Susi', 'Ibn Kathir', 'Abu Amr', 'Ibn Amir'];
    
    fetch(API_BASE_URL + 'get-riwayahs.php')
        .then(r => r.json())
        .then(data => renderRiwayahOptions(data))
        .catch(() => renderRiwayahOptions(common));
}

function renderRiwayahOptions(list) {
    riwayahSelect.innerHTML = '<option value="">Select Riwayah...</option>';
    list.forEach(r => {
        const opt = document.createElement('option');
        opt.value = r;
        opt.textContent = r;
        riwayahSelect.appendChild(opt);
    });
}

async function loadSuggestions(riwayah) {
    if (!riwayah) return;
    
    try {
        const res = await fetch(API_BASE_URL + 'get-suggestions.php?riwayah=' + encodeURIComponent(riwayah));
        const suggestions = await res.json();
        
        if (suggestions.length > 0) {
            renderSuggestions(suggestions);
        }
    } catch (err) {
        console.error('Failed to load suggestions', err);
    }
}

function renderSuggestions(suggestions) {
    const container = document.getElementById('suggestionsList');
    container.innerHTML = '';
    
    suggestions.forEach(sugg => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'suggestion-chip';
        btn.textContent = sugg;
        btn.onclick = () => addTaskWithTitle(sugg);
        container.appendChild(btn);
    });
    
    suggestionsBox.classList.remove('hidden');
}

function addTaskWithTitle(title) {
    const tasks = tasksContainer.querySelectorAll('.task-item');
    const lastTask = tasks[tasks.length - 1];
    const lastTitle = lastTask.querySelector('.task-title');
    
    if (!lastTitle.value) {
        lastTitle.value = title;
        lastTask.querySelector('.task-description').focus();
    } else {
        addTask();
        const newTasks = tasksContainer.querySelectorAll('.task-item');
        const newTask = newTasks[newTasks.length - 1];
        newTask.querySelector('.task-title').value = title;
        newTask.querySelector('.task-description').focus();
    }
}

// ==================== TASKS ====================
function addTask() {
    taskCount++;
    const clone = taskTemplate.content.cloneNode(true);
    const item = clone.querySelector('.task-item');
    item.querySelector('.number').textContent = taskCount;
    
    const removeBtn = item.querySelector('.btn-remove-task');
    removeBtn.onclick = () => removeTask(item);
    if (taskCount === 1) removeBtn.style.display = 'none';
    
    tasksContainer.appendChild(item);
}

function removeTask(item) {
    if (tasksContainer.children.length > 1) {
        item.remove();
        renumberTasks();
    }
}

function renumberTasks() {
    const items = tasksContainer.querySelectorAll('.task-item');
    items.forEach((item, idx) => {
        item.querySelector('.number').textContent = idx + 1;
    });
    taskCount = items.length;
}

function getTasks() {
    const arr = [];
    document.querySelectorAll('.task-item').forEach(item => {
        const t = item.querySelector('.task-title').value.trim();
        const d = item.querySelector('.task-description').value.trim();
        if (t) arr.push({title: t, description: d});
    });
    return arr;
}

// ==================== EVENTS ====================
function setupEventListeners() {
    pageNumberInput.addEventListener('input', handlePageInput);
    
    riwayahSelect.addEventListener('change', () => {
        if (riwayahSelect.value) {
            loadSuggestions(riwayahSelect.value);
        }
    });
    
    document.getElementById('addTaskBtn').addEventListener('click', addTask);
    form.addEventListener('submit', handleSubmit);
    document.getElementById('submitAnother').addEventListener('click', handleSubmitAnother);
    document.getElementById('viewLogsBtn').addEventListener('click', showLogs);
    document.getElementById('closeLogs').addEventListener('click', () => {
        logsModal.classList.add('hidden');
    });
}

function handlePageInput() {
    const p = parseInt(pageNumberInput.value, 10);
    if (p >= 1 && p <= 604) {
        juzDisplay.value = 'Juz ' + getJuzFromPage(p);
    } else {
        juzDisplay.value = '';
    }
}

// ==================== SUBMIT ====================
async function handleSubmit(e) {
    e.preventDefault();
    
    const p = parseInt(pageNumberInput.value, 10);
    if (!p || p < 1 || p > 604) {
        alert('Enter valid page (1-604)');
        return;
    }
    
    if (!riwayahSelect.value) {
        alert('Select riwayah');
        return;
    }
    
    const tasks = getTasks();
    if (tasks.length === 0) {
        alert('Add at least one task');
        return;
    }
    
    const data = {
        page: p,
        riwayah: riwayahSelect.value,
        juz: getJuzFromPage(p),
        tasks: tasks
    };
    
    const btn = form.querySelector('.btn-submit');
    const txt = btn.querySelector('.btn-text');
    const loader = btn.querySelector('.btn-loader');
    txt.classList.add('hidden');
    loader.classList.remove('hidden');
    btn.disabled = true;
    
    try {
        // Save location
        await apiCall('save-location.php', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        
        // Submit tasks
        const res = await fetch(API_BASE_URL + 'submit.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await res.json();
        
        if (result.success) {
            // Log activity
            await apiCall('log-activity.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'submit_tasks',
                    page: p,
                    riwayah: riwayahSelect.value,
                    tasksCount: tasks.length
                })
            });
            
            savedPage = pageNumberInput.value;
            savedRiwayah = riwayahSelect.value;
            
            form.classList.add('hidden');
            successMessage.classList.remove('hidden');
            window.scrollTo(0, 0);
        } else {
            throw new Error(result.message);
        }
    } catch (err) {
        alert('Failed: ' + err.message);
        txt.classList.remove('hidden');
        loader.classList.add('hidden');
        btn.disabled = false;
    }
}

function handleSubmitAnother() {
    tasksContainer.innerHTML = '';
    taskCount = 0;
    addTask();
    
    pageNumberInput.value = savedPage;
    if (savedPage) {
        const p = parseInt(savedPage, 10);
        if (p >= 1 && p <= 604) {
            juzDisplay.value = 'Juz ' + getJuzFromPage(p);
        }
    }
    
    riwayahSelect.value = savedRiwayah;
    
    if (savedRiwayah) {
        loadSuggestions(savedRiwayah);
    }
    
    form.classList.remove('hidden');
    successMessage.classList.add('hidden');
    
    const btn = form.querySelector('.btn-submit');
    btn.disabled = false;
    btn.querySelector('.btn-text').classList.remove('hidden');
    btn.querySelector('.btn-loader').classList.add('hidden');
    
    const firstInput = tasksContainer.querySelector('.task-title');
    if (firstInput) firstInput.focus();
}

// ==================== LOGS ====================
async function showLogs() {
    try {
        const res = await apiCall('get-logs.php');
        const logs = await res.json();
        
        logsContainer.innerHTML = '';
        
        if (logs.length === 0) {
            logsContainer.innerHTML = '<p>No recent activity</p>';
        } else {
            logs.forEach(log => {
                const div = document.createElement('div');
                div.className = 'log-entry';
                const date = new Date(log.timestamp);
                div.innerHTML = `
                    <div class="log-time">${date.toLocaleString()}</div>
                    <div class="log-action">
                        Submitted ${log.tasksCount} task(s) for Page ${log.page}, ${log.riwayah}
                    </div>
                `;
                logsContainer.appendChild(div);
            });
        }
        
        logsModal.classList.remove('hidden');
    } catch (err) {
        alert('Failed to load logs');
    }
}

// ==================== INIT ====================
document.addEventListener('DOMContentLoaded', checkAuth);