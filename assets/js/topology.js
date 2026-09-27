/* ================================================================
 * VNDTES - Interactive Network Topology Builder
 * Refactored & Standardized
 * ================================================================ */

document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    /* ================================================================
     * DATA FROM troubleshoot.php
     * ================================================================ */
    const interfaces = Array.isArray(window.VNDTES_TOPOLOGY) ? window.VNDTES_TOPOLOGY : [];
    const requirements = Array.isArray(window.VNDTES_TOPOLOGY_REQUIREMENTS) ? window.VNDTES_TOPOLOGY_REQUIREMENTS : [];
    const scenarioConnections = Array.isArray(window.VNDTES_TOPOLOGY_CONNECTIONS) ? window.VNDTES_TOPOLOGY_CONNECTIONS : [];
    const initialStudentConnections = Array.isArray(window.VNDTES_TOPOLOGY_STUDENT_CONNECTIONS) ? window.VNDTES_TOPOLOGY_STUDENT_CONNECTIONS : [];

    /* ================================================================
     * DOM ELEMENTS
     * ================================================================ */
    const workspace = document.getElementById('topologyWorkspace');
    const canvas = document.getElementById('topologyCanvas');
    const svg = document.getElementById('topologySvg');
    const nodesContainer = document.getElementById('topologyNodes');
    const palette = document.getElementById('topologyEquipmentPalette');
    const builderShell = document.querySelector('.topology-builder-shell');
    const emptyState = document.getElementById('topologyEmptyState');
    const status = document.getElementById('topologyStatus');
    const modeBadge = document.getElementById('topologyModeBadge');
    const cancelButton = document.getElementById('topologyCancelConnection');
    const resetButton = document.getElementById('topologyResetWorkspace');

    if (!workspace || !canvas || !nodesContainer || !palette) {
        console.error("VNDTES Topology: Required DOM elements are missing.");
        return;
    }

    /* ================================================================
     * STATE
     * ================================================================ */
    const requestedCanEdit = workspace.dataset.canEdit === '1';
    const attemptStatus = String(workspace.dataset.status || '').trim().toLowerCase();

    // Submitted/completed attempts must always be read-only, even if the page
    // accidentally renders data-can-edit="1".
    const isSubmitted = attemptStatus === 'submitted' || attemptStatus === 'completed';
    const canEdit = requestedCanEdit && !isSubmitted;
    const attemptId = parseInt(workspace.dataset.attemptId || '0', 10);
    
    let selectedPort = null;
    let nextNodeId = 1;
    const topologyNodes = new Map();
    const studentConnections = initialStudentConnections.map(connection => ({ ...connection }));

    /* ================================================================
     * BASIC HELPERS
     * ================================================================ */
    function toInt(value) {
        const number = parseInt(value, 10);
        return Number.isNaN(number) ? 0 : number;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalize(value) {
        return String(value || '').trim().toLowerCase();
    }

    /* ================================================================
     * DEVICE TYPE & INTERFACE NORMALISATION
     * ================================================================ */
    function normalizeDeviceType(type) {
        const value = normalize(type);
        if (value.includes('router') || value.includes('gateway')) return 'router';
        if (value.includes('switch') || value.includes('catalyst')) return 'switch';
        if (value.includes('pc') || value.includes('computer') || value.includes('workstation') || value.includes('desktop')) return 'pc';
        if (value.includes('server')) return 'server';
        if (value.includes('firewall')) return 'firewall';
        if (value.includes('wireless') || value.includes('wifi') || value.includes('access point')) return 'wireless';
        return 'network';
    }

    function naturalInterfaceSort(a, b) {
        const nameA = String(a.interface_name || '');
        const nameB = String(b.interface_name || '');
        return nameA.localeCompare(nameB, undefined, { numeric: true, sensitivity: 'base' });
    }

    function requirementType(requirement) {
        return normalizeDeviceType(requirement?.device_type);
    }

    function requirementCode(requirement) {
        return normalize(requirement?.device_code);
    }

    function requiredQuantity(requirement) {
        return Math.max(toInt(requirement?.quantity || 1), 1);
    }

    function interfaceInstanceKey(iface) {
        const value = iface.device_instance_id ?? iface.scenario_device_instance_id ?? iface.instance_id ?? null;
        return (value === null || value === undefined || value === '') ? null : String(value);
    }

    /* ================================================================
     * SCENARIO INTERFACE INDEX
     * ================================================================ */
    const usedScenarioInterfaceIds = new Set();
    scenarioConnections.forEach(connection => {
        const interfaceA = toInt(connection.interface_a_id);
        const interfaceB = toInt(connection.interface_b_id);
        if (interfaceA) usedScenarioInterfaceIds.add(interfaceA);
        if (interfaceB) usedScenarioInterfaceIds.add(interfaceB);
    });

    function interfaceMatchesRequirement(iface, requirement) {
        if (normalizeDeviceType(iface.device_type) !== requirementType(requirement)) return false;
        const wantedCode = requirementCode(requirement);
        const actualCode = normalize(iface.device_code);
        if (wantedCode && actualCode && wantedCode !== actualCode) return false;
        return true;
    }

    function getInterfacesForDevice(requirement, instanceNumber) {
        const matching = interfaces.filter(iface => interfaceMatchesRequirement(iface, requirement));
        if (!matching.length) return [];

        const scenarioHasConnections = usedScenarioInterfaceIds.size > 0;
        let relevant = scenarioHasConnections
            ? matching.filter(iface => {
                const id = toInt(iface.interface_id);
                return (id && usedScenarioInterfaceIds.has(id));
            })
            : matching.slice();

        const instanceGroups = new Map();
        relevant.forEach(iface => {
            const key = interfaceInstanceKey(iface);
            if (key === null) return;
            if (!instanceGroups.has(key)) instanceGroups.set(key, []);
            instanceGroups.get(key).push(iface);
        });

        if (instanceGroups.size) {
            const keys = Array.from(instanceGroups.keys());
            const selectedKey = keys[instanceNumber - 1];
            if (selectedKey !== undefined) {
                const selected = instanceGroups.get(selectedKey).slice().sort(naturalInterfaceSort);
                return scenarioHasConnections ? selected : selected.slice(0, 1);
            }
        }

        const occurrenceMap = new Map();
        const assigned = [];
        relevant.forEach(iface => {
            const name = normalize(iface.interface_name);
            const occurrence = (occurrenceMap.get(name) || 0) + 1;
            occurrenceMap.set(name, occurrence);
            if (occurrence === instanceNumber) assigned.push(iface);
        });

        if (assigned.length) return assigned.sort(naturalInterfaceSort);

        if (instanceNumber === 1) {
            const first = relevant.slice().sort(naturalInterfaceSort)[0];
            return first ? [first] : [];
        }
        return [];
    }

    /* ================================================================
     * NETWORK DEVICE ICONS
     * ================================================================ */
    const VNDTESIcons = {
        aliasMap: {
            'pc': 'desktop', 'desktop': 'desktop', 'desktop pc': 'desktop', 'computer': 'desktop', 'workstation': 'desktop', 'end device': 'desktop',
            'notebook': 'laptop', 'macbook': 'laptop', 'laptop computer': 'laptop',
            'l2 switch': 'switch', 'l3 switch': 'switch', 'layer 2 switch': 'switch', 'layer 3 switch': 'switch', 'ethernet switch': 'switch',
            'access point': 'wireless', 'ap': 'wireless', 'wireless router': 'wireless', 'wireless access point': 'wireless', 'wifi': 'wireless', 'wlan': 'wireless',
            'network printer': 'printer',
            'internet': 'cloud', 'wan': 'cloud',
            'asa': 'firewall', 'ids': 'firewall', 'ips': 'firewall',
            'gateway': 'router'
        },
        normalizeType: function(type) {
            if (!type) return 'generic';
            let t = String(type).toLowerCase().trim().replace(/[-_]+/g, ' ').replace(/\\s+/g, ' ');
            return this.aliasMap[t] || t;
        },
        getIcon: function(type, width = 48, height = 48) {
            const normalized = this.normalizeType(type);
            const base = `xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="${width}" height="${height}" aria-hidden="true" class="network-device-svg"`;
            const icons = {
                'router': `<svg ${base}><ellipse cx="50" cy="30" rx="40" ry="15" fill="#1e40af" stroke="#1e3a8a" stroke-width="2"/><path d="M 10 30 L 10 70 A 40 15 0 0 0 90 70 L 90 30" fill="#3b82f6" stroke="#1e3a8a" stroke-width="2"/><path d="M 50 15 L 50 45 M 35 30 L 65 30" stroke="#ffffff" stroke-width="3" fill="none"/><polygon points="50,10 55,18 45,18" fill="#ffffff"/><polygon points="65,30 57,25 57,35" fill="#ffffff"/></svg>`,
                'switch': `<svg ${base}><rect x="10" y="30" width="80" height="40" rx="3" fill="#059669" stroke="#064e3b" stroke-width="2"/><polygon points="10,30 25,15 95,15 80,30" fill="#10b981" stroke="#064e3b" stroke-width="2"/><polygon points="95,15 95,55 80,70 80,30" fill="#047857" stroke="#064e3b" stroke-width="2"/><path d="M 25 45 L 75 45 M 25 55 L 75 55" stroke="#ffffff" stroke-width="3" fill="none"/><polygon points="75,41 82,45 75,49" fill="#ffffff"/><polygon points="25,51 18,55 25,59" fill="#ffffff"/></svg>`,
                'desktop': `<svg ${base}><rect x="20" y="20" width="60" height="40" rx="3" fill="#e2e8f0" stroke="#334155" stroke-width="3"/><rect x="25" y="25" width="50" height="30" fill="#0ea5e9"/><path d="M 40 60 L 60 60 L 65 80 L 35 80 Z" fill="#cbd5e1" stroke="#334155" stroke-width="2"/><line x1="25" y1="80" x2="75" y2="80" stroke="#334155" stroke-width="4"/></svg>`,
                'laptop': `<svg ${base}><rect x="15" y="25" width="70" height="45" rx="3" fill="#e2e8f0" stroke="#334155" stroke-width="3"/><rect x="20" y="30" width="60" height="35" fill="#0ea5e9"/><polygon points="5,75 95,75 85,70 15,70" fill="#94a3b8" stroke="#334155" stroke-width="3"/></svg>`,
                'server': `<svg ${base}><rect x="30" y="10" width="40" height="80" rx="2" fill="#475569" stroke="#1e293b" stroke-width="2"/><rect x="35" y="20" width="30" height="10" fill="#334155" stroke="#1e293b" stroke-width="1"/><rect x="35" y="35" width="30" height="10" fill="#334155" stroke="#1e293b" stroke-width="1"/><rect x="35" y="50" width="30" height="10" fill="#334155" stroke="#1e293b" stroke-width="1"/><circle cx="40" cy="25" r="2" fill="#22c55e"/><circle cx="40" cy="40" r="2" fill="#22c55e"/><circle cx="40" cy="55" r="2" fill="#ef4444"/></svg>`,
                'wireless': `<svg ${base}><rect x="25" y="50" width="50" height="15" rx="2" fill="#94a3b8" stroke="#334155" stroke-width="2"/><line x1="35" y1="50" x2="25" y2="25" stroke="#334155" stroke-width="3" stroke-linecap="round"/><line x1="65" y1="50" x2="75" y2="25" stroke="#334155" stroke-width="3" stroke-linecap="round"/><path d="M 40 15 A 15 15 0 0 1 60 15" stroke="#3b82f6" stroke-width="2" fill="none"/><path d="M 45 22 A 8 8 0 0 1 55 22" stroke="#3b82f6" stroke-width="2" fill="none"/></svg>`,
                'firewall': `<svg ${base}><rect x="15" y="15" width="70" height="70" fill="#ef4444" stroke="#7f1d1d" stroke-width="3"/><line x1="15" y1="38" x2="85" y2="38" stroke="#f87171" stroke-width="2"/><line x1="15" y1="62" x2="85" y2="62" stroke="#f87171" stroke-width="2"/><line x1="40" y1="15" x2="40" y2="38" stroke="#f87171" stroke-width="2"/><line x1="60" y1="38" x2="60" y2="62" stroke="#f87171" stroke-width="2"/><line x1="40" y1="62" x2="40" y2="85" stroke="#f87171" stroke-width="2"/></svg>`,
                'cloud': `<svg ${base}><path d="M 30 65 A 20 20 0 0 1 30 25 A 25 25 0 0 1 70 25 A 20 20 0 0 1 70 65 Z" fill="#0ea5e9" stroke="#0284c7" stroke-width="2"/></svg>`,
                'printer': `<svg ${base}><rect x="25" y="45" width="50" height="30" rx="2" fill="#cbd5e1" stroke="#475569" stroke-width="2"/><polygon points="35,45 65,45 60,20 40,20" fill="#ffffff" stroke="#475569" stroke-width="2"/><rect x="30" y="65" width="40" height="15" fill="#ffffff" stroke="#475569" stroke-width="2"/><line x1="35" y1="70" x2="65" y2="70" stroke="#94a3b8" stroke-width="1"/></svg>`,
                'generic': `<svg ${base}><rect x="20" y="20" width="60" height="60" rx="10" fill="#64748b" stroke="#334155" stroke-width="3"/><circle cx="50" cy="50" r="10" fill="#cbd5e1"/></svg>`
            };
            return icons[normalized] || icons['generic'];
        }
    };
    /* ================================================================
     * PALETTE
     * ================================================================ */
    function countNodesForRequirement(requirement) {
        const type = requirementType(requirement);
        const code = requirementCode(requirement);
        let count = 0;
        topologyNodes.forEach(node => {
            if (normalizeDeviceType(node.device_type) !== type) return;
            if (code && normalize(node.device_code) && normalize(node.device_code) !== code) return;
            count++;
        });
        return count;
    }

    function renderPalette() {
        if (!requirements.length) {
            const hasTopologyPayload = Array.isArray(window.VNDTES_TOPOLOGY_REQUIREMENTS);
            palette.innerHTML = `
                <div class="topology-palette-empty">
                    <div class="topology-palette-empty-icon">!</div>
                    <strong>${hasTopologyPayload ? 'No equipment requirements' : 'Equipment data unavailable'}</strong>
                    <div class="small mt-1">${hasTopologyPayload
                        ? 'This scenario has no equipment definition.'
                        : 'The page did not provide the expected topology requirements. Check the PHP data loader and script include.'}</div>
                </div>`;
            console.warn('VNDTES Topology: requirements are empty or were not supplied by troubleshoot.php.');
            return;
        }

        palette.innerHTML = '';
        requirements.forEach((requirement, index) => {
            const quantity = requiredQuantity(requirement);
            const added = countNodesForRequirement(requirement);
            const complete = added >= quantity;
            const type = normalizeDeviceType(requirement.device_type);
            const wrapper = document.createElement('div');
            
            wrapper.className = 'topology-palette-device';
            wrapper.innerHTML = `
                <div class="topology-palette-device-icon topology-icon-${type}">
                    ${VNDTESIcons.getIcon(requirement.device_type, 44, 44)}
                </div>
                <div class="topology-palette-device-info">
                    <div class="topology-palette-device-name">
                        ${escapeHtml(requirement.device_name || requirement.device_type || 'Network Device')}
                    </div>
                    <div class="topology-palette-device-meta">
                        ${escapeHtml(requirement.device_code || '')}
                        ${requirement.device_code ? '<span>·</span>' : ''}
                        ${escapeHtml(requirement.device_type || '')}
                    </div>
                    <div class="topology-palette-count">${added} / ${quantity} added</div>
                </div>
                <button type="button" class="topology-add-device-btn" data-requirement-index="${index}" ${complete || !canEdit ? 'disabled' : ''}>
                    ${complete ? '✓ Complete' : '+ Add'}
                </button>
            `;

            const button = wrapper.querySelector('.topology-add-device-btn');
            if (button) button.addEventListener('click', () => addRequiredDevice(requirement));
            palette.appendChild(wrapper);
        });
    }

    function addRequiredDevice(requirement) {
        if (!canEdit) return;
        const quantity = requiredQuantity(requirement);
        const existing = countNodesForRequirement(requirement);

        if (existing >= quantity) {
            setStatus(`Only ${quantity} ${requirement.device_type}(s) are required.`, 'warning');
            return;
        }

        const node = createTopologyNode(requirement, existing + 1);
        topologyNodes.set(node.id, node);
        renderTopology();
        renderPalette();
        updateEmptyState();
        setStatus(`${requirement.device_type} ${existing + 1} added. Place it on the workspace.`, 'success');
    }

    function createTopologyNode(requirement, instanceNumber) {
        const deviceType = requirement.device_type || 'Network Device';
        const deviceName = requirement.device_name || deviceType;
        const deviceCode = requirement.device_code || deviceType.toUpperCase();
        const index = topologyNodes.size;
        const column = index % 3;
        const row = Math.floor(index / 3);

        return {
            id: `node-${nextNodeId++}`,
            device_type: deviceType,
            device_name: deviceName,
            device_code: deviceCode,
            instance: instanceNumber,
            x: 70 + (column * 360),
            y: 70 + (row * 260),
            interfaces: getInterfacesForDevice(requirement, instanceNumber)
        };
    }

    /* ================================================================
     * COMPLETED / READ-ONLY TOPOLOGY
     * ================================================================ */
    function createCompletedTopology() {
        topologyNodes.clear();
        nextNodeId = 1;
        if (!requirements.length) return;

        requirements.forEach(requirement => {
            const quantity = requiredQuantity(requirement);
            for (let instanceNumber = 1; instanceNumber <= quantity; instanceNumber++) {
                const node = createTopologyNode(requirement, instanceNumber);
                topologyNodes.set(node.id, node);
            }
        });

        const columns = { router: 120, firewall: 120, wireless: 500, switch: 500, server: 900, pc: 900, network: 500 };
        const rowCounters = new Map();

        topologyNodes.forEach(node => {
            const type = normalizeDeviceType(node.device_type);
            const column = columns[type] ?? columns.network;
            const row = rowCounters.get(type) || 0;
            node.x = column;
            node.y = 90 + (row * 230);
            rowCounters.set(type, row + 1);
        });

        const nodes = Array.from(topologyNodes.values());
        if (nodes.length > 1 && nodes.every(node => normalizeDeviceType(node.device_type) === 'router')) {
            nodes.forEach((node, index) => {
                node.x = 250 + ((index % 3) * 360);
                node.y = 150 + (Math.floor(index / 3) * 250);
            });
        }
    }

    function renderCompletedTopology() {
        createCompletedTopology();
        renderTopology();
    }

    function renderTopology() {
        nodesContainer.innerHTML = '';
        clearSvg();
        topologyNodes.forEach(node => renderNode(node));
        renderConnections();
    }

    /* ================================================================
     * RENDER DEVICE NODE
     * ================================================================ */
    function renderNode(node) {
        const element = document.createElement('div');
        element.className = 'vndtes-topology-node';
        element.dataset.nodeId = node.id;
        element.style.left = `${node.x}px`;
        element.style.top = `${node.y}px`;

        const type = normalizeDeviceType(node.device_type);
        const portsHtml = node.interfaces.length
            ? node.interfaces.map(iface => renderInterface(iface, node)).join('')
            : `<div class="topology-no-interfaces">No scenario interface assigned.</div>`;

        element.innerHTML = `
            <div class="topology-device-card">
                <div class="topology-device-header">
                    ${canEdit
                        ? `<div class="topology-device-drag-handle" title="Drag device"><span class="topology-drag-grip">⋮⋮</span></div>`
                        : `<div class="topology-device-drag-handle topology-readonly-grip" aria-hidden="true"><span class="topology-drag-grip">⋮⋮</span></div>`
                    }
                    <div class="topology-device-icon topology-icon-${type}">
                        ${VNDTESIcons.getIcon(node.device_type, 42, 42)}
                    </div>
                    <div class="topology-device-title">
                        <div class="topology-device-name">${escapeHtml(node.device_name)} ${node.instance}</div>
                        <div class="topology-device-code">${escapeHtml(node.device_code)} #${node.instance} <span>·</span> ${escapeHtml(node.device_type)}</div>
                    </div>
                    ${canEdit ? `<button type="button" class="topology-device-remove" data-remove-node="${node.id}" title="Remove device">×</button>` : ''}
                </div>
                <div class="topology-device-ports">${portsHtml}</div>
            </div>
        `;

        const removeButton = element.querySelector('[data-remove-node]');
        if (removeButton) {
            removeButton.addEventListener('click', event => {
                event.stopPropagation();
                removeNode(node.id);
            });
        }

        enableDragging(element, node);
        nodesContainer.appendChild(element);
    }

    function renderInterface(iface, node) {
        const interfaceId = toInt(iface.interface_id);
        const connected = isInterfaceConnected(interfaceId);
        
        return `
            <button type="button" class="topology-port ${connected ? 'is-connected' : ''}" 
                data-interface-id="${interfaceId}" data-node-id="${node.id}" 
                title="${connected ? 'Interface already connected' : `Connect ${escapeHtml(iface.interface_name)}`}"
                ${connected ? 'aria-disabled="true"' : ''} ${!canEdit ? 'disabled' : ''}>
                <span class="topology-port-led"></span>
                <span class="topology-port-name">${escapeHtml(iface.interface_name || 'Interface')}</span>
            </button>
        `;
    }

    /* ================================================================
     * CONNECTIONS & API
     * ================================================================ */
    function connectionUsesInterface(connection, interfaceId) {
        const id = toInt(interfaceId);
        return toInt(connection.interface_a_id) === id || toInt(connection.interface_b_id) === id;
    }

    function isInterfaceConnected(interfaceId) {
        const connections = canEdit ? studentConnections : scenarioConnections;
        return connections.some(connection => connectionUsesInterface(connection, interfaceId));
    }

    function findStudentConnection(connectionId) {
        return studentConnections.find(connection => toInt(connection.student_connection_id) === toInt(connectionId)) || null;
    }

    nodesContainer.addEventListener('click', event => {
        const port = event.target.closest('.topology-port');
        if (!port || !canEdit) return;
        event.preventDefault();

        const interfaceId = toInt(port.dataset.interfaceId);
        if (!interfaceId) return;

        if (isInterfaceConnected(interfaceId)) {
            setStatus('That interface is already connected. Remove its existing connection first.', 'warning');
            return;
        }

        if (!selectedPort) {
            selectedPort = { interfaceId, nodeId: port.dataset.nodeId };
            port.classList.add('selected');
            if (cancelButton) cancelButton.disabled = false;
            setStatus('Interface selected. Now click the interface port you want to connect to.', 'warning');
            return;
        }

        if (selectedPort.interfaceId === interfaceId) {
            setStatus('Choose a different interface.', 'warning');
            return;
        }

        const first = selectedPort;
        clearSelectedPort();
        createStudentConnection(first.interfaceId, interfaceId);
    });

    function clearSelectedPort() {
        selectedPort = null;
        nodesContainer.querySelectorAll('.topology-port.selected').forEach(port => port.classList.remove('selected'));
        if (cancelButton) cancelButton.disabled = true;
    }

    async function postTopologyAction(payload) {
        if (!attemptId || attemptId < 1) {
            throw new Error('The troubleshooting attempt ID is missing. Reload the page or contact the administrator.');
        }
        const response = await fetch('topology_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(payload)
        });

        let data;
        try {
            data = await response.json();
        } catch (_) {
            throw new Error(`The topology server returned an invalid response (HTTP ${response.status}). Check the PHP error log and endpoint response.`);
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Topology operation failed.');
        }
        return data;
    }

    async function createStudentConnection(interfaceA, interfaceB) {
        if (!canEdit) return;
        if (isInterfaceConnected(interfaceA) || isInterfaceConnected(interfaceB)) {
            setStatus('One of the selected interfaces is already connected.', 'warning');
            return;
        }

        setStatus('Creating network connection...', 'muted');
        try {
            const data = await postTopologyAction({
                action: 'create_connection',
                attempt_id: String(attemptId),
                interface_a_id: String(interfaceA),
                interface_b_id: String(interfaceB)
            });

            if (data.connection) studentConnections.push({ ...data.connection });
            renderTopology();
            setStatus(data.message || 'Connection created successfully.', 'success');
        } catch (error) {
            setStatus(error.message || 'Unable to create network connection.', 'danger');
        }
    }

    async function removeStudentConnection(connection) {
        if (!canEdit || !connection) return false;
        try {
            await postTopologyAction({
                action: 'remove_connection',
                attempt_id: String(attemptId),
                student_connection_id: String(connection.student_connection_id)
            });
            const index = studentConnections.indexOf(connection);
            if (index >= 0) studentConnections.splice(index, 1);
            return true;
        } catch (error) {
            setStatus(error.message || 'Unable to remove connection.', 'danger');
            return false;
        }
    }

    function renderConnections() {
        clearSvg();
        if (canEdit) {
            studentConnections.forEach(connection => drawConnection(connection, 'student'));
            return;
        }
        
        if (scenarioConnections.length) {
            scenarioConnections.forEach(connection => drawConnection(connection, 'scenario'));
            return;
        }
        
        studentConnections.forEach(connection => drawConnection(connection, 'student'));
    }

    function drawConnection(connection, type = 'student') {
        if (!svg) return;
        const pointA = findInterfacePosition(connection.interface_a_id);
        const pointB = findInterfacePosition(connection.interface_b_id);
        if (!pointA || !pointB) return;

        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', pointA.x);
        line.setAttribute('y1', pointA.y);
        line.setAttribute('x2', pointB.x);
        line.setAttribute('y2', pointB.y);
        line.classList.add('topology-connection-line', type);

        if (type === 'student') {
            line.dataset.connectionId = String(connection.student_connection_id || '');
            line.style.pointerEvents = 'stroke';
            line.style.cursor = 'pointer';
            line.addEventListener('click', async event => {
                event.stopPropagation();
                const stored = findStudentConnection(connection.student_connection_id);
                if (!stored) return;
                
                if (!window.confirm('Remove this network connection?')) return;
                setStatus('Removing network connection...', 'muted');
                const removed = await removeStudentConnection(stored);
                if (removed) {
                    renderTopology();
                    setStatus('Network connection removed.', 'success');
                }
            });
        } else if (type === 'scenario') {
            line.style.pointerEvents = 'none';
            line.style.cursor = 'default';
            line.style.stroke = '#0d6efd';
            line.style.strokeWidth = '5';
            line.style.opacity = '0.9';
        }
        svg.appendChild(line);
    }

    function findInterfacePosition(interfaceId) {
        const port = nodesContainer.querySelector(`.topology-port[data-interface-id="${toInt(interfaceId)}"]`);
        if (!port) return null;

        const canvasRect = canvas.getBoundingClientRect();
        const portRect = port.getBoundingClientRect();
        return {
            x: portRect.left - canvasRect.left + (portRect.width / 2),
            y: portRect.top - canvasRect.top + (portRect.height / 2)
        };
    }

    function clearSvg() {
        if (svg) svg.innerHTML = '';
    }

    async function removeNode(nodeId) {
        if (!canEdit) return;
        const node = topologyNodes.get(nodeId);
        if (!node) return;

        const interfaceIds = node.interfaces.map(iface => toInt(iface.interface_id));
        const relatedConnections = studentConnections.filter(connection => interfaceIds.some(id => connectionUsesInterface(connection, id)));

        if (relatedConnections.length) {
            setStatus('Removing the device connections...', 'muted');
            for (const connection of [...relatedConnections]) {
                const removed = await removeStudentConnection(connection);
                if (!removed) return;
            }
        }

        topologyNodes.delete(nodeId);
        clearSelectedPort();
        renderTopology();
        renderPalette();
        updateEmptyState();
        setStatus(`${node.device_type} ${node.instance} removed from the workspace.`, 'success');
    }

    /* ================================================================
     * DRAGGING
     * ================================================================ */
    function enableDragging(element, node) {
        if (!canEdit) return;
        const handle = element.querySelector('.topology-device-drag-handle');
        if (!handle) return;

        let dragging = false;
        let startX = 0, startY = 0, originalX = 0, originalY = 0;

        handle.addEventListener('pointerdown', event => {
            event.preventDefault();
            dragging = true;
            startX = event.clientX;
            startY = event.clientY;
            originalX = node.x;
            originalY = node.y;
            element.classList.add('is-dragging');
            handle.setPointerCapture(event.pointerId);
        });

        handle.addEventListener('pointermove', event => {
            if (!dragging) return;
            node.x = Math.max(10, originalX + (event.clientX - startX));
            node.y = Math.max(10, originalY + (event.clientY - startY));
            element.style.left = `${node.x}px`;
            element.style.top = `${node.y}px`;
            renderConnections();
        });

        const stopDragging = event => {
            if (!dragging) return;
            dragging = false;
            element.classList.remove('is-dragging');
            try { handle.releasePointerCapture(event.pointerId); } catch (_) {}
        };

        handle.addEventListener('pointerup', stopDragging);
        handle.addEventListener('pointercancel', stopDragging);
    }

    /* ================================================================
     * UI STATE & INITIALISE
     * ================================================================ */
    function updateEmptyState() {
        if (emptyState) emptyState.style.display = topologyNodes.size ? 'none' : '';
    }

    function setStatus(message, type = 'muted') {
        if (!status) return;
        status.textContent = message;
        status.classList.remove('is-success', 'is-warning', 'is-danger', 'is-muted', 'is-error');
        status.classList.add(`is-${type}`);
    }

    if (cancelButton) {
        cancelButton.addEventListener('click', () => {
            clearSelectedPort();
            setStatus('Connection selection cancelled.', 'muted');
        });
    }

    if (resetButton) {
        resetButton.addEventListener('click', async () => {
            if (!canEdit || !topologyNodes.size) return;
            if (!window.confirm('Reset the topology board? All student equipment and student connections on this board will be removed.')) return;
            
            setStatus('Resetting topology workspace...', 'muted');
            for (const connection of [...studentConnections]) {
                const removed = await removeStudentConnection(connection);
                if (!removed) return;
            }

            topologyNodes.clear();
            clearSelectedPort();
            renderTopology();
            renderPalette();
            updateEmptyState();
            setStatus('Topology workspace has been reset.', 'success');
        });
    }

    window.addEventListener('resize', () => renderConnections());

    function initialise() {
        if (isSubmitted) {
            if (modeBadge) modeBadge.textContent = 'Completed Network';
            if (palette) palette.style.display = 'none';
            if (builderShell) builderShell.style.gridTemplateColumns = 'minmax(0, 1fr)';
            if (cancelButton) cancelButton.style.display = 'none';
            if (resetButton) resetButton.style.display = 'none';

            renderCompletedTopology();
            updateEmptyState();
            
            setStatus(topologyNodes.size 
                ? 'Troubleshooting completed. The correct scenario network is shown below in read-only mode.' 
                : 'Troubleshooting completed. No scenario equipment was available to display.', 
            'success');
            return;
        }

        renderPalette();
        renderTopology();
        updateEmptyState();
        setStatus(canEdit ? 'Start by adding the required equipment from the palette.' : 'This troubleshooting attempt is read-only.', 'muted');
    }

    // Safely launch
    try {
        initialise();
    } catch (error) {
        console.error("VNDTES Topology initialization failed:", error);
        setStatus("System error: Unable to load topology data.", "danger");
    }
});