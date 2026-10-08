<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Project & Workload Summary Report</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
            background-color: #f8fafc;
            line-height: 1.5;
            padding: 30px;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 24px;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 26px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .header p {
            color: #64748b;
            font-size: 14px;
        }
        .meta-info {
            text-align: right;
            font-size: 13px;
            color: #475569;
        }
        .meta-info strong {
            color: #0f172a;
        }
        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #4f46e5;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s;
            margin-bottom: 20px;
        }
        .btn-print:hover {
            background: #4338ca;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 35px;
        }
        .stat-card {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 18px;
        }
        .stat-card .label {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .stat-card .value {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 30px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-bottom: 30px;
        }
        th, td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        tr:hover {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-gray { background: #f1f5f9; color: #475569; }

        .progress-bar {
            width: 100%;
            background-color: #e2e8f0;
            border-radius: 9999px;
            height: 8px;
            overflow: hidden;
            margin-top: 4px;
        }
        .progress-fill {
            height: 100%;
            border-radius: 9999px;
            background-color: #4f46e5;
        }
        .footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            margin-top: 40px;
            font-size: 12px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .container {
                box-shadow: none;
                border: none;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="/admin/reports" style="color: #64748b; text-decoration: none; font-size: 14px;">&larr; Back to Dashboard</a>
            <button class="btn-print" onclick="window.print()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Print / Save as PDF
            </button>
        </div>

        <div class="header">
            <div>
                <h1>Executive Project & Workload Report</h1>
                <p>Enterprise Management System &bull; Confidential Executive Brief</p>
            </div>
            <div class="meta-info">
                <p>Generated: <strong>{{ $generatedAt }}</strong></p>
                <p>Prepared by: <strong>{{ $generatedBy }}</strong></p>
            </div>
        </div>

        <!-- High-level KPI Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Total Projects</div>
                <div class="value">{{ $stats['total_projects'] }} <span style="font-size: 14px; font-weight: normal; color: #64748b;">({{ $stats['active_projects'] }} active)</span></div>
            </div>
            <div class="stat-card">
                <div class="label">Portfolio Budget</div>
                <div class="value">${{ number_format($stats['total_budget'], 2) }}</div>
            </div>
            <div class="stat-card">
                <div class="label">Total Tasks</div>
                <div class="value">{{ $stats['total_tasks'] }} <span style="font-size: 14px; font-weight: normal; color: #16a34a;">({{ $stats['completed_tasks'] }} done)</span></div>
            </div>
            <div class="stat-card">
                <div class="label">Overdue Tasks</div>
                <div class="value" style="color: {{ $stats['overdue_tasks'] > 0 ? '#dc2626' : '#16a34a' }};">
                    {{ $stats['overdue_tasks'] }}
                </div>
            </div>
        </div>

        <!-- 1. Project Progress Summary -->
        <div class="section-title">
            <span>Project Health & Progress</span>
            <span style="font-size: 13px; font-weight: normal; color: #64748b;">Total: {{ $projects->count() }} Projects</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Project Name</th>
                    <th>Client</th>
                    <th>Project Manager</th>
                    <th>Status</th>
                    <th>Budget</th>
                    <th style="width: 160px;">Progress</th>
                    <th>Deadline</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($projects as $project)
                    @php
                        $statusClass = match($project->status) {
                            'completed' => 'badge-success',
                            'in_progress' => 'badge-info',
                            'on_hold' => 'badge-warning',
                            'cancelled' => 'badge-danger',
                            default => 'badge-gray',
                        };
                    @endphp
                    <tr>
                        <td style="font-weight: 600;">{{ $project->code }}</td>
                        <td>{{ $project->name }}</td>
                        <td>{{ $project->client_company ?? 'Internal' }}</td>
                        <td>{{ $project->projectManager?->name ?? 'Unassigned' }}</td>
                        <td><span class="badge {{ $statusClass }}">{{ str_replace('_', ' ', $project->status) }}</span></td>
                        <td>${{ number_format($project->budget, 2) }}</td>
                        <td>
                            <div style="display: flex; justify-content: space-between; font-size: 11px; font-weight: 600;">
                                <span>{{ $project->progress }}%</span>
                                <span style="color: #64748b;">{{ $project->tasks->where('status', 'completed')->count() }}/{{ $project->tasks->count() }} Tasks</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: {{ $project->progress }}%;"></div>
                            </div>
                        </td>
                        <td>{{ $project->deadline ? $project->deadline->format('M d, Y') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- 2. Employee Workload & Capacity -->
        <div class="section-title">
            <span>Employee Workload & Capacity Tracking</span>
            <span style="font-size: 13px; font-weight: normal; color: #64748b;">Active Workforce: {{ $employees->count() }}</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Employee Name</th>
                    <th>Designation</th>
                    <th>Department</th>
                    <th>Active Projects</th>
                    <th>Assigned Tasks</th>
                    <th style="width: 160px;">Workload Allocation</th>
                    <th>Capacity Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($employees as $emp)
                    @php
                        $load = $emp->active_allocation_percentage;
                        $free = $emp->remaining_allocation_percentage;
                        $loadColor = $load >= 80 ? '#dc2626' : ($load >= 50 ? '#d97706' : '#16a34a');
                        $statusText = $load >= 100 ? 'Max Capacity' : ($load >= 80 ? 'Heavy Load' : ($load >= 50 ? 'Moderate' : 'Available'));
                        $badgeClass = $load >= 100 ? 'badge-danger' : ($load >= 80 ? 'badge-warning' : ($load >= 50 ? 'badge-info' : 'badge-success'));
                    @endphp
                    <tr>
                        <td style="font-weight: 600;">{{ $emp->name }}</td>
                        <td>{{ $emp->designation ?? '—' }}</td>
                        <td>{{ $emp->department?->name ?? 'Unassigned' }}</td>
                        <td>{{ $emp->allocations->whereNotIn('project.status', ['completed', 'cancelled'])->count() }}</td>
                        <td>{{ $emp->assignedTasks->count() }} ({{ $emp->assignedTasks->where('status', 'completed')->count() }} done)</td>
                        <td>
                            <div style="display: flex; justify-content: space-between; font-size: 11px; font-weight: 600;">
                                <span style="color: {{ $loadColor }};">{{ $load }}%</span>
                                <span style="color: #64748b;">{{ $free }}% free</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: {{ min(100, $load) }}%; background-color: {{ $loadColor }};"></div>
                            </div>
                        </td>
                        <td><span class="badge {{ $badgeClass }}">{{ $statusText }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            <span>Task & Project Management System &bull; Internal Report</span>
            <span>Page 1 of 1</span>
        </div>
    </div>
</body>
</html>
