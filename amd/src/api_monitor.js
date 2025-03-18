// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript for API Monitor
 *
 * @module     local_eventocoursecreation/api_monitor
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/chartjs', 'jquery'], function(Chart, $) {
    
    /**
     * Initialize the page
     *
     * @param {Object} data - The stats data
     */
    const init = function(data) {
        $(document).ready(function() {
            if (data && data.stats) {
                // Prepare data for charts
                const statsData = prepareStatsData(data.stats);
                
                // Initialize charts
                initializeApiCallsChart(statsData);
                initializeErrorsChart(statsData);
            }
        });
    };
    
    /**
     * Prepare data for charts
     *
     * @param {Object} stats - The stats data by Veranstalter
     * @return {Object} Prepared data for charts
     */
    const prepareStatsData = function(stats) {
        const labels = [];
        const apiCalls = [];
        const errors = [];
        const cacheHits = [];
        
        // Process each Veranstalter
        for (const veranstalterId in stats) {
            if (Object.prototype.hasOwnProperty.call(stats, veranstalterId)) {
                labels.push(veranstalterId);
                apiCalls.push(stats[veranstalterId].api_calls || 0);
                errors.push(stats[veranstalterId].errors || 0);
                cacheHits.push(stats[veranstalterId].cache_hits || 0);
            }
        }
        
        return {
            labels: labels,
            apiCalls: apiCalls,
            errors: errors,
            cacheHits: cacheHits
        };
    };
    
    /**
     * Initialize API calls chart
     *
     * @param {Object} data - Prepared data for charts
     */
    const initializeApiCallsChart = function(data) {
        const ctx = document.getElementById('api-calls-chart');
        if (!ctx) {
            return;
        }
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: M.util.get_string('api_calls', 'local_eventocoursecreation'),
                        data: data.apiCalls,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: M.util.get_string('api_cache_hits', 'local_eventocoursecreation'),
                        data: data.cacheHits,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: M.util.get_string('api_calls', 'local_eventocoursecreation')
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    };
    
    /**
     * Initialize errors chart
     *
     * @param {Object} data - Prepared data for charts
     */
    const initializeErrorsChart = function(data) {
        const ctx = document.getElementById('errors-chart');
        if (!ctx) {
            return;
        }
        
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        data: data.errors,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)',
                            'rgba(153, 102, 255, 0.5)',
                            'rgba(255, 159, 64, 0.5)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64, 1)'
                        ],
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: M.util.get_string('api_errors', 'local_eventocoursecreation')
                }
            }
        });
    };
    
    return {
        init: init
    };
});