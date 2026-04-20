// ─── Configuration ────────────────────────────────────────────────────────────
const API_URL = window.location.origin;
const TOKEN_KEY   = 'offlinesync_token';
const QUEUE_KEY   = 'offlinesync_queue';

// ─── État global ──────────────────────────────────────────────────────────────
let tasks       = [];
let authToken   = localStorage.getItem(TOKEN_KEY);
let offlineMode = false;   // toggle manuel pour simuler le mode hors ligne
let syncLog     = [];

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('loginForm').addEventListener('submit', handleLogin);

    if (!authToken) {
        showScreen('login');
        return;
    }
    await boot();
});

async function boot() {
    showScreen('app');
    renderQueue();
    renderSyncLog();
    updateConnectivityUI();
    await loadTasks();
    setupEventListeners();
    startConnectivityMonitoring();
}

function setupEventListeners() {
    document.getElementById('addTaskForm').addEventListener('submit', handleAddTask);
    document.getElementById('syncBtn').addEventListener('click', runFullSync);
    document.getElementById('logoutBtn').addEventListener('click', logout);
    document.getElementById('toggleOfflineBtn').addEventListener('click', toggleOfflineMode);
    document.getElementById('clearQueueBtn').addEventListener('click', clearQueue);
    document.getElementById('loginForm').addEventListener('submit', handleLogin);
}

// ─── Auth ─────────────────────────────────────────────────────────────────────
async function handleLogin(e) {
    e.preventDefault();
    const email    = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    const errEl    = document.getElementById('loginError');
    errEl.textContent = '';

    try {
        const res  = await apiFetch('/api/login', 'POST', { email, password }, false);
        authToken  = res.token;
        localStorage.setItem(TOKEN_KEY, authToken);
        await boot();
    } catch (err) {
        errEl.textContent = 'Identifiants incorrects.';
    }
}

function logout() {
    localStorage.removeItem(TOKEN_KEY);
    authToken = null;
    tasks = [];
    showScreen('login');
}

// ─── Offline queue (localStorage) ────────────────────────────────────────────
function getQueue() {
    try { return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]'); }
    catch { return []; }
}

function saveQueue(q) {
    localStorage.setItem(QUEUE_KEY, JSON.stringify(q));
}

function enqueue(resource, resourceId, operation, data) {
    const q = getQueue();
    q.push({
        id:          `local_${Date.now()}_${Math.random().toString(36).slice(2,7)}`,
        resource,
        resource_id: resourceId,
        operation,
        data,
        timestamp:   new Date().toISOString(),
        queued_at:   new Date().toISOString(),
    });
    saveQueue(q);
    renderQueue();
    addLog(`↑ Enqueued: ${operation} ${resource}`, 'queued');
}

function clearQueue() {
    if (!confirm('Vider la queue locale ? Les changements non synchronisés seront perdus.')) return;
    saveQueue([]);
    renderQueue();
    addLog('Queue vidée manuellement', 'info');
}

// ─── Fetch helper ─────────────────────────────────────────────────────────────
async function apiFetch(path, method = 'GET', body = null, auth = true) {
    if (offlineMode) throw new Error('Offline mode');

    const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
    if (auth && authToken) headers['Authorization'] = `Bearer ${authToken}`;

    const res = await fetch(API_URL + path, {
        method,
        headers,
        body: body ? JSON.stringify(body) : undefined,
    });

    if (res.status === 401) { logout(); throw new Error('Unauthorized'); }
    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw Object.assign(new Error(err.message || 'API error'), { data: err });
    }
    return res.json();
}

// ─── Tâches ──────────────────────────────────────────────────────────────────
async function loadTasks() {
    try {
        const data = await apiFetch('/api/tasks');
        tasks = data.data;
        renderTasks();
        updateStats(data.meta);
        addLog(`↓ Pull: ${tasks.length} tâche(s) chargée(s)`, 'success');
    } catch (err) {
        if (offlineMode || !navigator.onLine) {
            addLog('Mode offline – tâches depuis le cache local', 'warning');
            renderTasks();
        } else {
            showNotification('Erreur de chargement', 'error');
        }
    }
}

async function handleAddTask(e) {
    e.preventDefault();
    const payload = {
        title:       document.getElementById('title').value.trim(),
        description: document.getElementById('description').value.trim() || null,
        priority:    document.getElementById('priority').value,
        due_date:    document.getElementById('dueDate').value || null,
    };
    if (!payload.title) return;

    try {
        const data = await apiFetch('/api/tasks', 'POST', payload);
        tasks.unshift(data.data);
        showNotification('Tâche créée et synchronisée ✓', 'success');
        addLog(`✓ Create task "${payload.title}" → serveur`, 'success');
    } catch (err) {
        // Mode offline : queue locale
        const tempTask = {
            id:         `temp_${Date.now()}`,
            ...payload,
            completed:  false,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
            _pending:   true,
        };
        tasks.unshift(tempTask);
        enqueue('tasks', null, 'create', payload);
        showNotification('Tâche enregistrée localement (sync en attente)', 'warning');
    }

    document.getElementById('addTaskForm').reset();
    renderTasks();
    updateStats();
}

async function toggleTaskComplete(taskId) {
    const task = tasks.find(t => t.id == taskId);
    if (!task) return;

    const newState = !task.completed;
    task.completed = newState;
    renderTasks();

    try {
        await apiFetch(`/api/tasks/${taskId}/toggle`, 'POST');
        addLog(`✓ Toggle task ${taskId} → ${newState ? 'done' : 'pending'}`, 'success');
        showNotification(newState ? 'Tâche complétée ✓' : 'Tâche réactivée', 'success');
    } catch {
        enqueue('tasks', String(taskId), 'update', { completed: newState });
        showNotification('Modification mise en queue (offline)', 'warning');
    }
    updateStats();
}

async function deleteTask(taskId) {
    if (!confirm('Supprimer cette tâche ?')) return;

    tasks = tasks.filter(t => t.id != taskId);
    renderTasks();
    updateStats();

    try {
        await apiFetch(`/api/tasks/${taskId}`, 'DELETE');
        addLog(`✓ Delete task ${taskId} → serveur`, 'success');
        showNotification('Tâche supprimée', 'success');
    } catch {
        enqueue('tasks', String(taskId), 'delete', { id: taskId });
        showNotification('Suppression mise en queue (offline)', 'warning');
    }
}

// ─── Synchronisation complète ─────────────────────────────────────────────────
async function runFullSync() {
    const btn = document.getElementById('syncBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Sync...';
    document.getElementById('statusDot').classList.add('syncing');

    try {
        await pushQueue();
        await loadTasks();
        document.getElementById('pendingCount').textContent = getQueue().length;
        showNotification('Synchronisation complète ✓', 'success');
        addLog('─── Sync complète ───', 'info');
    } catch (err) {
        showNotification('Sync échouée – mode offline ?', 'error');
        addLog(`✗ Sync échouée: ${err.message}`, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '🔄 Synchroniser';
        document.getElementById('statusDot').classList.remove('syncing');
    }
}

async function pushQueue() {
    const q = getQueue();
    if (q.length === 0) {
        addLog('↑ Push: queue vide, rien à envoyer', 'info');
        return;
    }

    addLog(`↑ Push: envoi de ${q.length} item(s)...`, 'info');

    // Formatter pour l'API
    const items = q.map(item => ({
        resource:    item.resource,
        resource_id: item.resource_id,
        operation:   item.operation,
        data:        item.data,
        timestamp:   item.timestamp,
    }));

    const result = await apiFetch('/api/sync/push', 'POST', { items });

    addLog(`↑ Push résultat: synced=${result.synced} failed=${result.failed} conflicts=${result.conflicts.length}`,
           result.failed > 0 ? 'error' : 'success');

    if (result.conflicts.length > 0) {
        renderConflicts(result.conflicts);
        showNotification(`${result.conflicts.length} conflit(s) détecté(s)`, 'error');
    }

    // Vider la queue si tout s'est bien passé
    if (result.failed === 0) {
        saveQueue([]);
        renderQueue();
        // Nettoyer les tâches temporaires
        tasks = tasks.filter(t => !t._pending);
        renderTasks();
    } else {
        // Garder seulement les items qui ont échoué
        addLog(`✗ ${result.failed} item(s) non synchronisé(s)`, 'error');
    }
}

// ─── Mode offline ─────────────────────────────────────────────────────────────
function toggleOfflineMode() {
    offlineMode = !offlineMode;
    const btn = document.getElementById('toggleOfflineBtn');
    if (offlineMode) {
        btn.textContent = '✅ Simuler ONLINE';
        btn.style.background = '#e53e3e';
        showNotification('Mode OFFLINE activé', 'warning');
        addLog('⚠ Mode offline simulé activé', 'warning');
    } else {
        btn.textContent = '⚡ Simuler OFFLINE';
        btn.style.background = '';
        showNotification('Mode ONLINE rétabli', 'success');
        addLog('✓ Mode online rétabli', 'success');
        runFullSync();
    }
    updateConnectivityUI();
}

function startConnectivityMonitoring() {
    setInterval(updateConnectivityUI, 5000);
    window.addEventListener('online',  () => { updateConnectivityUI(); if (!offlineMode) { showNotification('Connexion rétablie', 'success'); runFullSync(); } });
    window.addEventListener('offline', () => { updateConnectivityUI(); showNotification('Connexion perdue', 'error'); });
}

function updateConnectivityUI() {
    const isOnline = !offlineMode && navigator.onLine;
    const dot  = document.getElementById('statusDot');
    const text = document.getElementById('statusText');
    dot.className  = 'status-dot' + (offlineMode ? ' simulated-offline' : isOnline ? '' : ' offline');
    text.textContent = offlineMode ? '⚡ Offline simulé' : isOnline ? 'Online' : 'Offline';
    document.getElementById('pendingCount').textContent = getQueue().length;
}

// ─── Rendu ────────────────────────────────────────────────────────────────────
function showScreen(name) {
    document.getElementById('loginScreen').style.display = name === 'login' ? 'block' : 'none';
    document.getElementById('appScreen').style.display   = name === 'app'   ? 'block' : 'none';
}

function renderTasks() {
    const el = document.getElementById('tasksList');
    if (tasks.length === 0) {
        el.innerHTML = `<div class="empty-state"><div class="empty-state-icon">📭</div><p>Aucune tâche</p></div>`;
        return;
    }
    el.innerHTML = tasks.map(task => `
        <div class="task-item ${task.completed ? 'completed' : ''} ${task._pending ? 'pending-sync' : ''}">
            <div class="task-checkbox ${task.completed ? 'checked' : ''}" onclick="toggleTaskComplete('${task.id}')"></div>
            <div class="task-content">
                <div class="task-title">${escapeHtml(task.title)}</div>
                ${task.description ? `<div class="task-description">${escapeHtml(task.description)}</div>` : ''}
                <div class="task-meta">
                    <span class="task-badge priority-${task.priority}">
                        ${ {high:'🔴',medium:'🟠',low:'🟢'}[task.priority] } ${task.priority}
                    </span>
                    ${task.due_date ? `<span class="task-badge ${isOverdue(task) ? 'overdue' : 'due-date'}">${formatDate(task.due_date)}</span>` : ''}
                    ${task._pending ? '<span class="task-badge pending-badge">⏳ En attente de sync</span>' : ''}
                </div>
            </div>
            <div class="task-actions">
                <div class="task-action-btn delete" onclick="deleteTask('${task.id}')">🗑️</div>
            </div>
        </div>
    `).join('');
}

function updateStats(meta) {
    if (meta) {
        document.getElementById('totalCount').textContent     = meta.total;
        document.getElementById('completedCount').textContent = meta.completed;
        document.getElementById('pendingTasksCount').textContent = meta.pending;
    } else {
        const total     = tasks.length;
        const completed = tasks.filter(t => t.completed).length;
        document.getElementById('totalCount').textContent     = total;
        document.getElementById('completedCount').textContent = completed;
        document.getElementById('pendingTasksCount').textContent = total - completed;
    }
}

function renderQueue() {
    const q  = getQueue();
    const el = document.getElementById('queueList');
    document.getElementById('queueCount').textContent = q.length;
    document.getElementById('pendingCount').textContent = q.length;

    if (q.length === 0) {
        el.innerHTML = '<div class="queue-empty">Queue vide ✓</div>';
        return;
    }
    el.innerHTML = q.map(item => `
        <div class="queue-item queue-${item.operation}">
            <span class="queue-op">${item.operation.toUpperCase()}</span>
            <span class="queue-resource">${item.resource}</span>
            ${item.resource_id ? `<span class="queue-id">#${item.resource_id}</span>` : '<span class="queue-id">new</span>'}
            <span class="queue-time">${formatTime(item.queued_at)}</span>
        </div>
    `).join('');
}

function renderConflicts(conflicts) {
    const el = document.getElementById('conflictList');
    if (!conflicts || conflicts.length === 0) {
        el.innerHTML = '<div class="queue-empty">Aucun conflit</div>';
        return;
    }
    el.innerHTML = conflicts.map(c => `
        <div class="conflict-item">
            <div class="conflict-resource">${c.resource} #${c.resource_id}</div>
            <div class="conflict-detail">
                <span>Local: ${formatTime(c.local_timestamp)}</span>
                <span>Serveur: ${formatTime(c.remote_timestamp)}</span>
            </div>
        </div>
    `).join('');
}

function addLog(message, type = 'info') {
    syncLog.unshift({ message, type, time: new Date().toISOString() });
    if (syncLog.length > 50) syncLog.pop();
    renderSyncLog();
}

function renderSyncLog() {
    const el = document.getElementById('syncLog');
    if (syncLog.length === 0) {
        el.innerHTML = '<div class="queue-empty">Aucun événement</div>';
        return;
    }
    el.innerHTML = syncLog.map(entry => `
        <div class="log-entry log-${entry.type}">
            <span class="log-time">${formatTime(entry.time)}</span>
            <span>${entry.message}</span>
        </div>
    `).join('');
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

function formatDate(d) {
    const diff = Math.floor((new Date(d) - new Date()) / 86400000);
    if (diff === 0) return 'Aujourd\'hui';
    if (diff === 1) return 'Demain';
    if (diff === -1) return 'Hier';
    if (diff < 0) return `Il y a ${Math.abs(diff)}j`;
    return `Dans ${diff}j`;
}

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function isOverdue(task) {
    return task.due_date && !task.completed && new Date(task.due_date) < new Date();
}

function showNotification(msg, type = 'success') {
    const el = document.getElementById('notification');
    el.textContent = msg;
    el.className = `notification ${type} show`;
    setTimeout(() => el.classList.remove('show'), 3500);
}
