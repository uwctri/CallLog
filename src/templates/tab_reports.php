<?php
/**
 * Tab 6: Reports & Analytics
 */
?>
<div class="tab-pane fade show active" x-show="activeTab === 'reports'" x-cloak>
    
    <!-- Controls Bar -->
    <div class="card border shadow-sm mb-4 rounded-3 bg-white">
        <div class="card-body py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="fw-bold text-dark small"><i class="fas fa-filter text-primary me-1.5"></i> Timeframe:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn" :class="reportTimeframe === '7d' ? 'btn-primary fw-bold' : 'btn-outline-secondary'" @click="setReportTimeframe('7d')">Last 7 Days</button>
                    <button type="button" class="btn" :class="reportTimeframe === '30d' ? 'btn-primary fw-bold' : 'btn-outline-secondary'" @click="setReportTimeframe('30d')">Last 30 Days</button>
                    <button type="button" class="btn" :class="reportTimeframe === '90d' ? 'btn-primary fw-bold' : 'btn-outline-secondary'" @click="setReportTimeframe('90d')">Last 90 Days</button>
                    <button type="button" class="btn" :class="reportTimeframe === 'all' ? 'btn-primary fw-bold' : 'btn-outline-secondary'" @click="setReportTimeframe('all')">All Time</button>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" @click="loadReportsData()" class="btn btn-outline-primary btn-sm px-3 shadow-sm d-inline-flex align-items-center" :disabled="reportsLoading">
                    <i class="fas fa-sync-alt me-1.5" :class="{ 'fa-spin': reportsLoading }"></i>
                    <span>Refresh</span>
                </button>
                <button type="button" @click="exportReportsCsv()" class="btn btn-outline-success btn-sm px-3 shadow-sm d-inline-flex align-items-center" :disabled="reportsLoading || !reportsData">
                    <i class="fas fa-file-csv me-1.5"></i>
                    <span>Export CSV</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div x-show="reportsLoading && !reportsData" class="text-center py-5">
        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
        <div class="text-muted fw-semibold">Crunching report analytics & caller stats...</div>
    </div>

    <!-- Reports Content -->
    <div x-show="reportsData" x-transition>
        
        <!-- KPI Overview Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border shadow-sm h-100 bg-white rounded-3">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-muted small fw-semibold text-uppercase tracking-wider">Logged Attempts</span>
                            <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                <i class="fas fa-headset"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1 text-dark" x-text="totalReportAttempts">0</h3>
                        <span class="text-muted small">Call instances logged</span>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card border shadow-sm h-100 bg-white rounded-3">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-muted small fw-semibold text-uppercase tracking-wider">Completed Calls</span>
                            <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                <i class="fas fa-check-double"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1 text-success" x-text="reportsData?.queueSummary?.completedCalls || 0">0</h3>
                        <span class="text-muted small" x-text="(reportsData?.queueSummary?.completionPercentage || 0) + '% overall completion rate'"></span>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card border shadow-sm h-100 bg-white rounded-3">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-muted small fw-semibold text-uppercase tracking-wider">Expired Reminders</span>
                            <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1 text-danger" x-text="reportsData?.queueSummary?.expiredReminders || 0">0</h3>
                        <span class="text-muted small">Passed without outreach</span>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card border shadow-sm h-100 bg-white rounded-3">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-muted small fw-semibold text-uppercase tracking-wider">Hourly Cron Health</span>
                            <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                 :class="reportsData?.cronDiagnostics?.temporalCron?.status === 'healthy' ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning'"
                                 style="width: 34px; height: 34px;">
                                <i class="fas" :class="reportsData?.cronDiagnostics?.temporalCron?.status === 'healthy' ? 'fa-check' : 'fa-exclamation-triangle'"></i>
                            </div>
                        </div>
                        <h5 class="fw-bold mb-1 text-dark" x-text="reportsData?.cronDiagnostics?.temporalCron?.status === 'healthy' ? 'Active & Healthy' : (reportsData?.cronDiagnostics?.temporalCron?.status === 'warning' ? 'Needs Attention' : 'Idle')">Active</h5>
                        <span class="text-muted small" x-text="'Last run: ' + (reportsData?.cronDiagnostics?.temporalCron?.lastRunFormatted || 'Never')"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Layout: Left = Caller Productivity, Right = Queue Breakdown & Cron -->
        <div class="row g-4 mb-4">
            
            <!-- Caller Productivity Table -->
            <div class="col-lg-8">
                <div class="card border shadow-sm rounded-3 h-100 bg-white">
                    <div class="card-header bg-light border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                        <div class="fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="fas fa-users text-primary"></i>
                            <span>Caller Productivity & Effort</span>
                        </div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" x-text="(sortedCallers.length) + ' Team Members'"></span>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th class="ps-4" style="cursor: pointer;" @click="sortReportBy('displayName')">
                                        Team Member <i class="fas fa-sort ms-1 opacity-50"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortReportBy('attempts')">
                                        Attempts <i class="fas fa-sort ms-1 opacity-50"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortReportBy('completed')">
                                        Completed <i class="fas fa-sort ms-1 opacity-50"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortReportBy('completionRate')">
                                        Success Rate <i class="fas fa-sort ms-1 opacity-50"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortReportBy('voicemails')">
                                        Voicemails <i class="fas fa-sort ms-1 opacity-50"></i>
                                    </th>
                                    <th class="text-center pe-4" style="cursor: pointer;" @click="sortReportBy('callbacksScheduled')">
                                        Callbacks Set <i class="fas fa-sort ms-1 opacity-50"></i>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="sortedCallers.length === 0">
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 opacity-50 d-block"></i>
                                            No call attempts or completions logged for this timeframe.
                                        </td>
                                    </tr>
                                </template>
                                <template x-for="user in sortedCallers" :key="user.username">
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                                     :class="user.isAutomation ? 'bg-secondary' : 'bg-primary'"
                                                     style="width: 28px; height: 28px; font-size: 0.75rem;">
                                                    <span x-text="user.isAutomation ? 'R' : user.displayName.charAt(0).toUpperCase()"></span>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark" x-text="user.displayName"></div>
                                                    <span class="text-muted" style="font-size: 0.75rem;" x-text="user.isAutomation ? 'Automated Logic' : '@' + user.username"></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center fw-semibold" x-text="user.attempts"></td>
                                        <td class="text-center fw-semibold text-success" x-text="user.completed"></td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-1.5">
                                                <div class="progress flex-grow-1" style="height: 6px; max-width: 60px;">
                                                    <div class="progress-bar bg-success" role="progressbar" :style="'width: ' + user.completionRate + '%'"></div>
                                                </div>
                                                <span class="fw-bold" x-text="user.completionRate + '%'"></span>
                                            </div>
                                        </td>
                                        <td class="text-center text-muted" x-text="user.voicemails"></td>
                                        <td class="text-center pe-4 text-muted" x-text="user.callbacksScheduled"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Call Queue & Pipeline Distribution -->
            <div class="col-lg-4">
                <div class="card border shadow-sm rounded-3 mb-4 bg-white">
                    <div class="card-header bg-light border-bottom py-3 px-4 fw-bold text-dark">
                        <i class="fas fa-chart-pie text-primary me-2"></i> Queue Status Distribution
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small fw-bold mb-1">
                                <span class="text-primary"><i class="fas fa-clock me-1"></i> Active / Open</span>
                                <span x-text="reportsData?.queueSummary?.activeCalls || 0"></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary" :style="'width: ' + ((reportsData?.queueSummary?.activeCalls / (reportsData?.queueSummary?.totalCalls || 1)) * 100) + '%'"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between small fw-bold mb-1">
                                <span class="text-success"><i class="fas fa-check-circle me-1"></i> Completed</span>
                                <span x-text="reportsData?.queueSummary?.completedCalls || 0"></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" :style="'width: ' + ((reportsData?.queueSummary?.completedCalls / (reportsData?.queueSummary?.totalCalls || 1)) * 100) + '%'"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between small fw-bold mb-1">
                                <span class="text-danger"><i class="fas fa-times-circle me-1"></i> Expired</span>
                                <span x-text="reportsData?.queueSummary?.expiredCalls || 0"></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-danger" :style="'width: ' + ((reportsData?.queueSummary?.expiredCalls / (reportsData?.queueSummary?.totalCalls || 1)) * 100) + '%'"></div>
                            </div>
                        </div>

                        <div class="pt-3 border-top d-flex justify-content-between text-muted small">
                            <span>Average Attempts per Call:</span>
                            <span class="fw-bold text-dark" x-text="reportsData?.queueSummary?.avgAttemptsPerCall || '0'"></span>
                        </div>
                    </div>
                </div>

                <!-- Template Breakdown Table -->
                <div class="card border shadow-sm rounded-3 bg-white">
                    <div class="card-header bg-light border-bottom py-3 px-4 fw-bold text-dark">
                        <i class="fas fa-layer-group text-primary me-2"></i> Breakdown by Call Template
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th class="ps-3">Template</th>
                                    <th class="text-center">Active</th>
                                    <th class="text-center">Done</th>
                                    <th class="text-center pe-3">Expired</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(tplStats, tplKey) in (reportsData?.queueSummary?.byTemplate || {})" :key="tplKey">
                                    <tr>
                                        <td class="ps-3 fw-semibold text-capitalize text-dark" x-text="tplKey === 'mcv' ? 'Missed Visit' : (tplKey === 'nts' ? 'Need to Schedule' : tplKey)"></td>
                                        <td class="text-center text-primary fw-bold" x-text="tplStats.active"></td>
                                        <td class="text-center text-success" x-text="tplStats.complete"></td>
                                        <td class="text-center pe-3 text-danger" x-text="tplStats.expired"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Automation & Cron Diagnostics Section -->
        <div class="card border shadow-sm rounded-3 bg-white mb-4">
            <div class="card-header bg-light border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                <div class="fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-robot text-primary"></i>
                    <span>Automation & Cron Diagnostics</span>
                </div>
                <span class="text-muted small"><i class="fas fa-info-circle me-1"></i> Background lifecycle health</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-4 mb-4">
                    <!-- Hourly Temporal Cron Diagnostic -->
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-history text-primary"></i>
                                    <h6 class="fw-bold mb-0 text-dark">Hourly Temporal Lifecycle Cron</h6>
                                </div>
                                <span class="badge" :class="reportsData?.cronDiagnostics?.temporalCron?.status === 'healthy' ? 'bg-success text-white' : 'bg-warning text-dark'"
                                      x-text="reportsData?.cronDiagnostics?.temporalCron?.status === 'healthy' ? 'Healthy' : 'Warning'"></span>
                            </div>
                            <div class="small text-muted mb-2">Expiring reminders, attendance-based MCV completions, and new record intake.</div>
                            <div class="d-flex justify-content-between small py-1 border-bottom">
                                <span class="text-muted">Last Execution:</span>
                                <span class="fw-bold text-dark" x-text="reportsData?.cronDiagnostics?.temporalCron?.lastRunFormatted || 'Never'"></span>
                            </div>
                            <div class="d-flex justify-content-between small py-1 border-bottom">
                                <span class="text-muted">Execution Duration:</span>
                                <span class="fw-bold text-dark" x-text="(reportsData?.cronDiagnostics?.temporalCron?.duration || '0') + 's'"></span>
                            </div>
                            <div class="d-flex justify-content-between small py-1">
                                <span class="text-muted">Records Evaluated / Updated:</span>
                                <span class="fw-bold text-dark" x-text="(reportsData?.cronDiagnostics?.temporalCron?.recordsEvaluated || 0) + ' eval / ' + (reportsData?.cronDiagnostics?.temporalCron?.callsUpdated || 0) + ' updated'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Daily Sync Cron Diagnostic -->
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-sync text-primary"></i>
                                    <h6 class="fw-bold mb-0 text-dark">Daily Synchronization & Audit Cron</h6>
                                </div>
                                <span class="badge" :class="reportsData?.cronDiagnostics?.dailySync?.status === 'healthy' ? 'bg-success text-white' : 'bg-warning text-dark'"
                                      x-text="reportsData?.cronDiagnostics?.dailySync?.status === 'healthy' ? 'Healthy' : 'Warning'"></span>
                            </div>
                            <div class="small text-muted mb-2">Comprehensive sweep of all templates across every project record.</div>
                            <div class="d-flex justify-content-between small py-1 border-bottom">
                                <span class="text-muted">Last Execution:</span>
                                <span class="fw-bold text-dark" x-text="reportsData?.cronDiagnostics?.dailySync?.lastRunFormatted || 'Never'"></span>
                            </div>
                            <div class="d-flex justify-content-between small py-1 border-bottom">
                                <span class="text-muted">Execution Duration:</span>
                                <span class="fw-bold text-dark" x-text="(reportsData?.cronDiagnostics?.dailySync?.duration || '0') + 's'"></span>
                            </div>
                            <div class="d-flex justify-content-between small py-1">
                                <span class="text-muted">Records Evaluated / Generated:</span>
                                <span class="fw-bold text-dark" x-text="(reportsData?.cronDiagnostics?.dailySync?.recordsEvaluated || 0) + ' eval / ' + (reportsData?.cronDiagnostics?.dailySync?.callsGenerated || 0) + ' generated'"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Automated Logs -->
                <div class="fw-bold small text-dark mb-2"><i class="fas fa-list-alt text-secondary me-1.5"></i> Recent Automation Activity Log</div>
                <div class="table-responsive border rounded-3">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light text-muted">
                            <tr>
                                <th class="ps-3" style="width: 140px;">Timestamp</th>
                                <th style="width: 90px;">Record</th>
                                <th style="width: 130px;">Action</th>
                                <th>Call / Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="!reportsData?.cronDiagnostics?.recentSystemLogs || reportsData?.cronDiagnostics?.recentSystemLogs?.length === 0">
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-muted">No recent system automation logs recorded.</td>
                                </tr>
                            </template>
                            <template x-for="log in (reportsData?.cronDiagnostics?.recentSystemLogs || [])" :key="log.logId">
                                <tr>
                                    <td class="ps-3 text-muted" x-text="log.timestamp"></td>
                                    <td><span class="badge bg-light text-dark border" x-text="'#' + log.record"></span></td>
                                    <td>
                                        <span class="badge" 
                                              :class="log.action === 'call_generated' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 
                                                      (log.action === 'call_auto_completed' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border') "
                                              x-text="log.action || 'system_event'"></span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark" x-text="log.callName ? log.callName + ' — ' : ''"></span>
                                        <span class="text-muted" x-text="log.reason || log.message"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>
