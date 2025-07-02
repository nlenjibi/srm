/**
 * Student Management System - Main JavaScript File
 * Handles AJAX requests, form submissions, and interactive features
 */

// Global configuration
const Config = {
    baseUrl: window.location.origin + '/student-management',
    apiUrl: '/api',
    timeout: 30000
};

// Utility functions
const Utils = {
    // Show loading spinner
    showLoading: function(element) {
        element.innerHTML = '<span class="spinner"></span> Loading...';
        element.disabled = true;
    },

    // Hide loading spinner
    hideLoading: function(element, originalText) {
        element.innerHTML = originalText;
        element.disabled = false;
    },

    // Show alert message
    showAlert: function(message, type = 'info', duration = 5000) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
        `;
        
        const container = document.querySelector('.content') || document.body;
        container.insertBefore(alertDiv, container.firstChild);
        
        if (duration > 0) {
            setTimeout(() => {
                alertDiv.remove();
            }, duration);
        }
    },

    // Format date
    formatDate: function(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString();
    },

    // Validate form
    validateForm: function(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        return isValid;
    },

    // Serialize form data
    serializeForm: function(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        return data;
    }
};

// AJAX helper class
class AjaxHelper {
    static async request(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            timeout: Config.timeout
        };

        const requestOptions = { ...defaultOptions, ...options };

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), requestOptions.timeout);

            const response = await fetch(url, {
                ...requestOptions,
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            } else {
                return await response.text();
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                throw new Error('Request timeout');
            }
            throw error;
        }
    }

    static async get(url, params = {}) {
        const urlParams = new URLSearchParams(params);
        const fullUrl = urlParams.toString() ? `${url}?${urlParams}` : url;
        return this.request(fullUrl);
    }

    static async post(url, data = {}) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    static async put(url, data = {}) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    static async delete(url) {
        return this.request(url, {
            method: 'DELETE'
        });
    }

    static async submitForm(form) {
        const formData = new FormData(form);
        const url = form.action || window.location.href;
        
        return this.request(url, {
            method: form.method || 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
    }
}

// Student Management Class
class StudentManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadStudents();
    }

    bindEvents() {
        // Student form submission
        const studentForm = document.getElementById('studentForm');
        if (studentForm) {
            studentForm.addEventListener('submit', (e) => this.handleStudentSubmit(e));
        }

        // Search students
        const searchInput = document.getElementById('searchStudents');
        if (searchInput) {
            let timeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => this.searchStudents(e.target.value), 300);
            });
        }

        // Class filter
        const classFilter = document.getElementById('classFilter');
        if (classFilter) {
            classFilter.addEventListener('change', (e) => this.filterByClass(e.target.value));
        }
    }

    async loadStudents(filters = {}) {
        try {
            const students = await AjaxHelper.get(`${Config.apiUrl}/students.php`, filters);
            this.renderStudentTable(students);
        } catch (error) {
            Utils.showAlert('Error loading students: ' + error.message, 'danger');
        }
    }

    async handleStudentSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        if (!Utils.validateForm(form)) {
            Utils.showAlert('Please fill in all required fields', 'danger');
            return;
        }
        
        try {
            Utils.showLoading(submitBtn);
            
            const response = await AjaxHelper.submitForm(form);
            
            if (response.success) {
                Utils.showAlert(response.message, 'success');
                form.reset();
                this.loadStudents();
                
                // Close modal if exists
                const modal = form.closest('.modal');
                if (modal) {
                    this.closeModal(modal);
                }
            } else {
                Utils.showAlert(response.message || 'An error occurred', 'danger');
            }
        } catch (error) {
            Utils.showAlert('Error: ' + error.message, 'danger');
        } finally {
            Utils.hideLoading(submitBtn, originalText);
        }
    }

    async searchStudents(query) {
        await this.loadStudents({ search: query });
    }

    async filterByClass(className) {
        await this.loadStudents({ class: className });
    }

    renderStudentTable(students) {
        const tableBody = document.getElementById('studentsTableBody');
        if (!tableBody) return;

        if (!students || students.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center">No students found</td></tr>';
            return;
        }

        const rows = students.map(student => `
            <tr>
                <td>${student.student_id}</td>
                <td>${student.first_name} ${student.last_name}</td>
                <td>${student.class}</td>
                <td>${student.section || '-'}</td>
                <td>${student.phone || '-'}</td>
                <td>${student.parent_name || '-'}</td>
                <td>
                    <span class="badge ${student.status === 'active' ? 'bg-success' : 'bg-danger'}">
                        ${student.status}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="studentManager.editStudent(${student.id})">
                        Edit
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="studentManager.deleteStudent(${student.id})">
                        Delete
                    </button>
                </td>
            </tr>
        `).join('');

        tableBody.innerHTML = rows;
    }

    async editStudent(id) {
        try {
            const student = await AjaxHelper.get(`${Config.apiUrl}/students.php?id=${id}`);
            this.populateStudentForm(student);
            this.showModal('studentModal');
        } catch (error) {
            Utils.showAlert('Error loading student data: ' + error.message, 'danger');
        }
    }

    async deleteStudent(id) {
        if (!confirm('Are you sure you want to delete this student?')) {
            return;
        }

        try {
            const response = await AjaxHelper.delete(`${Config.apiUrl}/students.php?id=${id}`);
            
            if (response.success) {
                Utils.showAlert(response.message, 'success');
                this.loadStudents();
            } else {
                Utils.showAlert(response.message || 'Failed to delete student', 'danger');
            }
        } catch (error) {
            Utils.showAlert('Error: ' + error.message, 'danger');
        }
    }

    populateStudentForm(student) {
        const form = document.getElementById('studentForm');
        if (!form) return;

        Object.keys(student).forEach(key => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) {
                field.value = student[key] || '';
            }
        });
    }

    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
        }
    }

    closeModal(modal) {
        modal.classList.remove('show');
    }
}

// Grade Management Class
class GradeManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        // Grade form submission
        const gradeForm = document.getElementById('gradeForm');
        if (gradeForm) {
            gradeForm.addEventListener('submit', (e) => this.handleGradeSubmit(e));
        }

        // Calculate percentage automatically
        const marksObtained = document.getElementById('marks_obtained');
        const totalMarks = document.getElementById('total_marks');
        
        if (marksObtained && totalMarks) {
            [marksObtained, totalMarks].forEach(input => {
                input.addEventListener('input', () => this.calculateGrade());
            });
        }
    }

    async handleGradeSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        if (!Utils.validateForm(form)) {
            Utils.showAlert('Please fill in all required fields', 'danger');
            return;
        }
        
        try {
            Utils.showLoading(submitBtn);
            
            const response = await AjaxHelper.submitForm(form);
            
            if (response.success) {
                Utils.showAlert(response.message, 'success');
                form.reset();
                this.loadGrades();
            } else {
                Utils.showAlert(response.message || 'An error occurred', 'danger');
            }
        } catch (error) {
            Utils.showAlert('Error: ' + error.message, 'danger');
        } finally {
            Utils.hideLoading(submitBtn, originalText);
        }
    }

    calculateGrade() {
        const marksObtained = parseFloat(document.getElementById('marks_obtained')?.value || 0);
        const totalMarks = parseFloat(document.getElementById('total_marks')?.value || 0);
        
        if (totalMarks > 0) {
            const percentage = (marksObtained / totalMarks) * 100;
            let grade = 'F';
            
            if (percentage >= 90) grade = 'A';
            else if (percentage >= 80) grade = 'B';
            else if (percentage >= 70) grade = 'C';
            else if (percentage >= 60) grade = 'D';
            
            const gradeField = document.getElementById('grade');
            if (gradeField) {
                gradeField.value = grade;
            }
        }
    }

    async loadGrades(filters = {}) {
        try {
            const grades = await AjaxHelper.get(`${Config.apiUrl}/grades.php`, filters);
            this.renderGradeTable(grades);
        } catch (error) {
            Utils.showAlert('Error loading grades: ' + error.message, 'danger');
        }
    }

    renderGradeTable(grades) {
        const tableBody = document.getElementById('gradesTableBody');
        if (!tableBody) return;

        if (!grades || grades.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center">No grades found</td></tr>';
            return;
        }

        const rows = grades.map(grade => `
            <tr>
                <td>${grade.student_name}</td>
                <td>${grade.subject_name}</td>
                <td>${grade.exam_type}</td>
                <td>${grade.marks_obtained}/${grade.total_marks}</td>
                <td>${((grade.marks_obtained / grade.total_marks) * 100).toFixed(1)}%</td>
                <td>
                    <span class="badge ${this.getGradeBadgeClass(grade.grade)}">
                        ${grade.grade}
                    </span>
                </td>
                <td>${Utils.formatDate(grade.exam_date)}</td>
            </tr>
        `).join('');

        tableBody.innerHTML = rows;
    }

    getGradeBadgeClass(grade) {
        switch (grade) {
            case 'A': return 'bg-success';
            case 'B': return 'bg-primary';
            case 'C': return 'bg-warning';
            case 'D': return 'bg-info';
            default: return 'bg-danger';
        }
    }
}

// Attendance Management Class
class AttendanceManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        // Attendance form submission
        const attendanceForm = document.getElementById('attendanceForm');
        if (attendanceForm) {
            attendanceForm.addEventListener('submit', (e) => this.handleAttendanceSubmit(e));
        }

        // Mark all present/absent buttons
        const markAllPresentBtn = document.getElementById('markAllPresent');
        const markAllAbsentBtn = document.getElementById('markAllAbsent');
        
        if (markAllPresentBtn) {
            markAllPresentBtn.addEventListener('click', () => this.markAllAttendance('present'));
        }
        
        if (markAllAbsentBtn) {
            markAllAbsentBtn.addEventListener('click', () => this.markAllAttendance('absent'));
        }
    }

    async handleAttendanceSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        try {
            Utils.showLoading(submitBtn);
            
            const response = await AjaxHelper.submitForm(form);
            
            if (response.success) {
                Utils.showAlert(response.message, 'success');
                this.loadAttendance();
            } else {
                Utils.showAlert(response.message || 'An error occurred', 'danger');
            }
        } catch (error) {
            Utils.showAlert('Error: ' + error.message, 'danger');
        } finally {
            Utils.hideLoading(submitBtn, originalText);
        }
    }

    markAllAttendance(status) {
        const checkboxes = document.querySelectorAll(`input[name="attendance[]"][value="${status}"]`);
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
    }

    async loadAttendance(filters = {}) {
        try {
            const attendance = await AjaxHelper.get(`${Config.apiUrl}/attendance.php`, filters);
            this.renderAttendanceTable(attendance);
        } catch (error) {
            Utils.showAlert('Error loading attendance: ' + error.message, 'danger');
        }
    }

    renderAttendanceTable(attendance) {
        const tableBody = document.getElementById('attendanceTableBody');
        if (!tableBody) return;

        if (!attendance || attendance.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center">No attendance records found</td></tr>';
            return;
        }

        const rows = attendance.map(record => `
            <tr>
                <td>${record.student_name}</td>
                <td>${record.subject_name}</td>
                <td>${Utils.formatDate(record.attendance_date)}</td>
                <td>
                    <span class="badge ${this.getAttendanceClass(record.status)}">
                        ${record.status}
                    </span>
                </td>
                <td>${record.remarks || '-'}</td>
            </tr>
        `).join('');

        tableBody.innerHTML = rows;
    }

    getAttendanceClass(status) {
        switch (status) {
            case 'present': return 'bg-success';
            case 'late': return 'bg-warning';
            case 'absent': return 'bg-danger';
            default: return 'bg-secondary';
        }
    }
}

// Dashboard functionality
class Dashboard {
    constructor() {
        this.init();
    }

    init() {
        this.loadDashboardStats();
        this.loadRecentActivities();
        this.setupRealTimeUpdates();
    }

    async loadDashboardStats() {
        try {
            const stats = await AjaxHelper.get(`${Config.apiUrl}/dashboard.php`);
            this.updateStatsCards(stats);
        } catch (error) {
            console.error('Error loading dashboard stats:', error);
        }
    }

    updateStatsCards(stats) {
        const updateCard = (id, value) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        };

        updateCard('totalStudents', stats.total_students || 0);
        updateCard('totalTeachers', stats.total_teachers || 0);
        updateCard('totalSubjects', stats.total_subjects || 0);
        updateCard('activeClasses', stats.active_classes || 0);
    }

    async loadRecentActivities() {
        try {
            const activities = await AjaxHelper.get(`${Config.apiUrl}/activities.php`);
            this.renderRecentActivities(activities);
        } catch (error) {
            console.error('Error loading recent activities:', error);
        }
    }

    renderRecentActivities(activities) {
        const container = document.getElementById('recentActivities');
        if (!container) return;

        if (!activities || activities.length === 0) {
            container.innerHTML = '<p class="text-center text-muted">No recent activities</p>';
            return;
        }

        const activityList = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-content">
                    <h6>${activity.title}</h6>
                    <p class="text-muted">${activity.description}</p>
                    <small class="text-muted">${Utils.formatDate(activity.created_at)}</small>
                </div>
            </div>
        `).join('');

        container.innerHTML = activityList;
    }

    setupRealTimeUpdates() {
        // Update dashboard stats every 5 minutes
        setInterval(() => {
            this.loadDashboardStats();
        }, 300000);
    }
}

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize managers based on current page
    const currentPage = window.location.pathname;
    
    if (currentPage.includes('dashboard')) {
        window.dashboard = new Dashboard();
    }
    
    // Initialize student manager on relevant pages
    if (currentPage.includes('student') || currentPage.includes('manage_students')) {
        window.studentManager = new StudentManager();
    }
    
    // Initialize grade manager on relevant pages
    if (currentPage.includes('grade')) {
        window.gradeManager = new GradeManager();
    }
    
    // Initialize attendance manager on relevant pages
    if (currentPage.includes('attendance')) {
        window.attendanceManager = new AttendanceManager();
    }
    
    // Global event listeners
    setupGlobalEventListeners();
});

// Global event listeners
function setupGlobalEventListeners() {
    // Modal close functionality
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-close') || e.target.classList.contains('modal')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.classList.remove('show');
            }
        }
    });
    
    // Sidebar toggle for mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }
    
    // Auto-hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (!alert.querySelector('.btn-close')) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        });
    }, 5000);
}

// Export for global access
window.Utils = Utils;
window.AjaxHelper = AjaxHelper;