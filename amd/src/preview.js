define(['core/notification', 'core/str', 'core/templates', 'core/ajax'], function(Notification, Str, Templates, Ajax) {
    const Selectors = {
        sections: {
            bulkCreation: '#id_runnowheader',
            individualCreation: '#id_individualcreationheader',
            individualContainer: '#id_individualcreationheadercontainer'
        },
        controls: {
            bulkForce: '#id_force',
            individualForce: '.force-create',
            bulkCreate: '#id_runnow',
            createSingle: '.create-single',
            courseSelect: '.course-select',
            createSelected: '.create-selected',
            selectAllEligible: '#select-all-eligible',
            forceAll: '#force-all'
        },
        regions: {
            previewGrid: '.course-grid',
            courseCard: '.course-preview-card',
            previewContainer: '#evento-preview-content',
            courseItem: '.course-item'
        },
        counters: {
            selectedCount: '#selected-count',
            totalCount: '#total-count',
            selectedBadge: '.selected-count-badge'
        },
        filters: {
            buttons: '.filter-buttons .btn',
            active: '.filter-buttons .btn.active'
        },
        sorts: {
            select: '.sort-select'
        }
    };

    let cachedPreviewData = null;
    let previewContainer = null;
    let categoryId = null;
    let isCreatingCourse = false;


    /**
     * Format a timestamp to a readable date
     * @param {number} timestamp
     * @return {string} Formatted date
     */
    const formatDate = (timestamp) => {
        return new Date(timestamp * 1000).toLocaleString();
    };

    /**
     * Render preview content from data
     * @return {Promise} Promise that resolves when rendering is complete
     */
    const renderPreviewContent = async () => {
        if (!previewContainer) {
            console.error('Preview container not initialized');
            return;
        }

        // Check the response structure and courses availability
        if (!cachedPreviewData?.status || !Array.isArray(cachedPreviewData.courses)) {
            previewContainer.innerHTML = `
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    ${cachedPreviewData?.message || M.util.get_string('nocoursestocreate', 'local_eventocoursecreation')}
                </div>`;
            return;
        }

        if (cachedPreviewData.courses.length === 0) {
            previewContainer.innerHTML = `
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    ${M.util.get_string('nocoursestocreate', 'local_eventocoursecreation')}
                </div>`;
            return;
        }

        try {
            const templateContext = {
                courses: cachedPreviewData.courses,
                showCreateSelected: true,
                hasSelectedCourses: cachedPreviewData.courses.some(course => course.canCreate)
            };

            const template = await Templates.render('local_eventocoursecreation/preview_grid', templateContext);
            previewContainer.innerHTML = template;

            // Initialize handlers for newly rendered content
            initializePreviewHandlers();

        } catch (error) {
            console.error('Error rendering preview content:', error);
            previewContainer.innerHTML = `
                <div class="alert alert-danger">
                    ${M.util.get_string('previewerror', 'local_eventocoursecreation')}
                </div>`;
            Notification.exception(error);
        }
    };

    /**
     * Load preview data from the server
     * @param {number} catId Category ID
     * @param {boolean} refresh Whether to force refresh
     * @return {Promise} Promise that resolves with preview data
     */
    const loadPreviewData = async (catId, refresh = false) => {
        try {
            const formData = new FormData();
            formData.append('categoryid', catId);
            formData.append('sesskey', M.cfg.sesskey);
            if (refresh) {
                formData.append('refresh', 1);
            }
            
            previewContainer.innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">${M.util.get_string('loading', 'local_eventocoursecreation')}</span>
                    </div>
                    <p class="mt-2">${M.util.get_string('loadingcourselist', 'local_eventocoursecreation')}</p>
                </div>
            `;
            
            const response = await fetch(`${M.cfg.wwwroot}/local/eventocoursecreation/preview.php`, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            cachedPreviewData = await response.json();
            
            if (!cachedPreviewData.status) {
                throw new Error(cachedPreviewData.message || 'Failed to load preview data');
            }

            // If the section is already expanded, render immediately
            const section = document.querySelector(Selectors.sections.individualCreation);
            if (section && !section.classList.contains('collapsed')) {
                await renderPreviewContent();
            }

            return cachedPreviewData;
        } catch (error) {
            console.error('Preview load failed:', error);
            if (previewContainer) {
                previewContainer.innerHTML = `
                    <div class="alert alert-danger">
                        ${error.message || M.util.get_string('previewloadfailed', 'local_eventocoursecreation')}
                    </div>`;
            }
            return null;
        }
    };

    /**
     * Create a single course
     * @param {number} eventId Event ID
     * @param {boolean} force Whether to force creation
     * @param {HTMLElement} button The button that triggered creation
     * @return {Promise} Promise that resolves when course creation is complete
     */
    const createCourse = async (eventId, force, button) => {
        if (isCreatingCourse) {
            return;
        }
        
        isCreatingCourse = true;
        const originalText = button.innerHTML;
        const card = button.closest(Selectors.regions.courseCard);
        
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
        button.disabled = true;
        
        if (card) {
            card.classList.add('creating');
        }

        try {
            const formData = new FormData();
            formData.append('eventid', eventId);
            formData.append('force', force ? 1 : 0);
            formData.append('categoryid', categoryId);
            formData.append('sesskey', M.cfg.sesskey);

            console.log(formData);
            
            const response = await fetch(`${M.cfg.wwwroot}/local/eventocoursecreation/create_course.php`, {
                method: 'POST',
                body: formData
            });

            await console.log(response);
            
            const result = await response.json();
            
            if (!result.status) {
                throw new Error(result.message || M.util.get_string('creationfailed', 'local_eventocoursecreation'));
            }

            if (card) {
                // Remove from DOM with animation
                card.classList.add('created');
                setTimeout(() => {
                    // Remove the course from cached data
                    cachedPreviewData.courses = cachedPreviewData.courses.filter(
                        course => course.eventId !== parseInt(eventId)
                    );
                    
                    const item = card.closest(Selectors.regions.courseItem);
                    if (item) {
                        item.style.opacity = 0;
                        setTimeout(() => {
                            item.remove();
                            updateCreateSelectedButton();
                            updateSelectionCount();
                        }, 300);
                    }
                }, 500);
            }
            
            Notification.addNotification({
                message: result.message || M.util.get_string('creationsuccessful', 'local_eventocoursecreation'),
                type: 'success'
            });

        } catch (error) {
            button.innerHTML = originalText;
            button.disabled = false;
            
            if (card) {
                card.classList.remove('creating');
                card.classList.add('error');
                setTimeout(() => {
                    card.classList.remove('error');
                }, 2000);
            }
            
            Notification.exception(error);
        } finally {
            isCreatingCourse = false;
        }
    };

    /**
     * Create multiple courses
     * @param {Array} courses Courses to create
     * @param {HTMLElement} button Button that triggered creation
     * @return {Promise} Promise that resolves when all courses are created
     */
    const createMultipleCourses = async (courses, button) => {
        if (isCreatingCourse || courses.length === 0) {
            return;
        }
        
        isCreatingCourse = true;
        const originalText = button.innerHTML;
        button.innerHTML = `<i class="fa fa-spinner fa-spin"></i> ${M.util.get_string('creating', 'local_eventocoursecreation')}`;
        button.disabled = true;

        const results = [];
        let successful = 0;
        let failed = 0;
        
        for (const course of courses) {
            try {
                // Add visual indicator for the current course
                const courseCard = document.querySelector(`.course-item[data-event-id="${course.eventId}"] ${Selectors.regions.courseCard}`);
                if (courseCard) {
                    courseCard.classList.add('creating');
                }
                
                const formData = new FormData();
                formData.append('eventid', course.eventId);
                formData.append('force', course.force ? 1 : 0);
                formData.append('categoryid', categoryId);
                formData.append('sesskey', M.cfg.sesskey);

                const response = await fetch(`${M.cfg.wwwroot}/local/eventocoursecreation/create_course.php`, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                results.push({ status: result.status, message: result.message });
                
                if (result.status) {
                    successful++;
                    
                    // Visual success indicator
                    if (courseCard) {
                        courseCard.classList.add('created');
                        setTimeout(() => {
                            const item = courseCard.closest(Selectors.regions.courseItem);
                            if (item) {
                                item.style.opacity = 0;
                                setTimeout(() => item.remove(), 300);
                            }
                        }, 500);
                    }
                    
                    // Remove created course from cached data
                    cachedPreviewData.courses = cachedPreviewData.courses.filter(
                        c => c.eventId !== course.eventId
                    );
                } else {
                    failed++;
                    // Visual error indicator
                    if (courseCard) {
                        courseCard.classList.remove('creating');
                        courseCard.classList.add('error');
                        setTimeout(() => {
                            courseCard.classList.remove('error');
                        }, 2000);
                    }
                }
            } catch (error) {
                results.push({ status: false, message: error.message });
                failed++;
                
                // Find and reset the card
                const courseCard = document.querySelector(`.course-item[data-event-id="${course.eventId}"] ${Selectors.regions.courseCard}`);
                if (courseCard) {
                    courseCard.classList.remove('creating');
                    courseCard.classList.add('error');
                    setTimeout(() => {
                        courseCard.classList.remove('error');
                    }, 2000);
                }
            }
        }

        // Show result notifications
        if (successful > 0) {
            Notification.addNotification({
                message: M.util.get_string('creationsuccessfulcount', 'local_eventocoursecreation', successful),
                type: 'success'
            });
        }
        
        if (failed > 0) {
            Notification.addNotification({
                message: M.util.get_string('creationfailedcount', 'local_eventocoursecreation', failed),
                type: 'error'
            });
        }

        // Reset button and update UI
        button.innerHTML = originalText;
        button.disabled = true;
        isCreatingCourse = false;
        
        // Update selection count and create button state
        updateSelectionCount();
        updateCreateSelectedButton();
    };

    /**
     * Update course selection state based on force checkbox
     * @param {HTMLElement} courseCard The course card element
     */
    const updateCourseSelectionState = (courseCard) => {
        const checkbox = courseCard.querySelector(Selectors.controls.courseSelect);
        const forceCheckbox = courseCard.querySelector(Selectors.controls.individualForce);
        const createButton = courseCard.querySelector(Selectors.controls.createSingle);
        const isForced = forceCheckbox?.checked || false;
        
        const courseItem = courseCard.closest(Selectors.regions.courseItem);
        if (!courseItem) return;
        
        const eventId = courseItem.dataset.eventId;
        const course = cachedPreviewData.courses.find(c => c.eventId === parseInt(eventId));

        if (course) {
            if (!course.canCreate && !isForced) {
                checkbox.checked = false;
                checkbox.disabled = true;
                createButton.disabled = true;
            } else {
                checkbox.disabled = false;
                createButton.disabled = false;
            }
        }

        updateCreateSelectedButton();
    };

    /**
     * Update the create selected button state
     */
    const updateCreateSelectedButton = () => {
        const createSelectedBtn = document.querySelector(Selectors.controls.createSelected);
        if (!createSelectedBtn) return;

        const selectedCourses = document.querySelectorAll(`${Selectors.controls.courseSelect}:checked`);
        createSelectedBtn.disabled = selectedCourses.length === 0;
        
        // Update badge count
        const badge = document.querySelector(Selectors.counters.selectedBadge);
        if (badge) {
            badge.textContent = selectedCourses.length;
        }
    };

    /**
     * Update selection counters
     */
    const updateSelectionCount = () => {
        const selectedCourses = document.querySelectorAll(`${Selectors.controls.courseSelect}:checked`);
        const totalCourses = document.querySelectorAll(Selectors.controls.courseSelect);
        
        const selectedCountEl = document.querySelector(Selectors.counters.selectedCount);
        const totalCountEl = document.querySelector(Selectors.counters.totalCount);
        
        if (selectedCountEl) {
            selectedCountEl.textContent = selectedCourses.length;
        }
        
        if (totalCountEl) {
            totalCountEl.textContent = totalCourses.length;
        }
    };

    /**
     * Handle force checkbox change
     * @param {Event} e The change event
     */
    const handleForceChange = (e) => {
        const courseCard = e.target.closest(Selectors.regions.courseCard);
        if (courseCard) {
            updateCourseSelectionState(courseCard);
        }
    };

    /**
     * Handle course selection checkbox change
     * @param {Event} e The change event
     */
    const handleCourseSelection = (e) => {
        updateCreateSelectedButton();
        updateSelectionCount();
    };

    /**
     * Create selected courses
     * @return {Promise} Promise that resolves when all courses are created
     */
    const createSelectedCourses = async () => {
        const selectedCards = document.querySelectorAll(`${Selectors.controls.courseSelect}:checked`);
        const courses = Array.from(selectedCards).map(checkbox => {
            const item = checkbox.closest(Selectors.regions.courseItem);
            if (!item) return null;
            
            const card = checkbox.closest(Selectors.regions.courseCard);
            const forceCheckbox = card.querySelector(Selectors.controls.individualForce);
            
            return {
                eventId: parseInt(item.dataset.eventId),
                force: forceCheckbox.checked
            };
        }).filter(Boolean);

        if (courses.length === 0) {
            return;
        }

        const createSelectedBtn = document.querySelector(Selectors.controls.createSelected);
        await createMultipleCourses(courses, createSelectedBtn);
    };

    /**
     * Apply filter to course list
     * @param {string} filterType The filter type
     */
    const applyFilter = (filterType) => {
        const items = document.querySelectorAll(Selectors.regions.courseItem);
        
        items.forEach(item => {
            const status = item.dataset.status;
            
            switch(filterType) {
                case 'all':
                    item.style.display = '';
                    break;
                case 'ready':
                    item.style.display = status === 'ready' ? '' : 'none';
                    break;
                case 'blocked':
                    item.style.display = status === 'blocked' ? '' : 'none';
                    break;
            }
        });
        
        // Update active button
        document.querySelectorAll(Selectors.filters.buttons).forEach(btn => {
            btn.classList.remove('active');
        });
        
        const activeButton = document.querySelector(`${Selectors.filters.buttons}[data-filter="${filterType}"]`);
        if (activeButton) {
            activeButton.classList.add('active');
        }
    };

    /**
     * Apply sort to course list
     * @param {string} sortType The sort type
     */
    const applySortBy = (sortType) => {
        const grid = document.querySelector(Selectors.regions.previewGrid);
        if (!grid) return;
        
        const items = Array.from(document.querySelectorAll(Selectors.regions.courseItem));
        
        items.sort((a, b) => {
            switch(sortType) {
                case 'status':
                    // Status sorting: Ready first, then blocked
                    if (a.dataset.status !== b.dataset.status) {
                        return a.dataset.status === 'ready' ? -1 : 1;
                    }
                    // Fall through to date sorting if status is the same
                case 'date':
                    return parseInt(a.dataset.date) - parseInt(b.dataset.date);
                case 'name':
                    return a.dataset.name.localeCompare(b.dataset.name);
                default:
                    return 0;
            }
        });
        
        // Reorder DOM elements
        items.forEach(item => {
            grid.appendChild(item);
        });
    };

    /**
     * Handle select all eligible checkbox
     * @param {Event} e The change event
     */
    const handleSelectAllEligible = (e) => {
        const isChecked = e.target.checked;
        
        document.querySelectorAll(Selectors.regions.courseItem).forEach(item => {
            const checkbox = item.querySelector(Selectors.controls.courseSelect);
            if (checkbox && !checkbox.disabled) {
                checkbox.checked = isChecked;
            }
        });
        
        updateCreateSelectedButton();
        updateSelectionCount();
    };

    /**
     * Handle force all checkbox
     * @param {Event} e The change event
     */
    const handleForceAll = (e) => {
        const isChecked = e.target.checked;
        
        document.querySelectorAll(Selectors.controls.individualForce).forEach(checkbox => {
            checkbox.checked = isChecked;
            
            // Update dependent elements
            const card = checkbox.closest(Selectors.regions.courseCard);
            if (card) {
                updateCourseSelectionState(card);
            }
        });
        
        updateCreateSelectedButton();
        updateSelectionCount();
    };

    /**
     * Initialize all preview handlers
     */
    const initializePreviewHandlers = () => {
        const container = document.querySelector(Selectors.regions.previewContainer);
        if (!container) return;

        // Force creation toggles
        container.querySelectorAll(Selectors.controls.individualForce).forEach(checkbox => {
            checkbox.addEventListener('change', handleForceChange);
        });

        // Course selection checkboxes
        container.querySelectorAll(Selectors.controls.courseSelect).forEach(checkbox => {
            checkbox.addEventListener('change', handleCourseSelection);
        });

        // Single course creation buttons
        container.querySelectorAll(Selectors.controls.createSingle).forEach(button => {
            button.addEventListener('click', async (e) => {
                const item = e.target.closest(Selectors.regions.courseItem);
                if (!item) return;
                
                const eventId = parseInt(item.dataset.eventId);
                const card = e.target.closest(Selectors.regions.courseCard);
                const forceCheckbox = card.querySelector(Selectors.controls.individualForce);
                
                await createCourse(eventId, forceCheckbox.checked, e.target);
            });
        });

        // Create selected button
        const createSelectedBtn = container.querySelector(Selectors.controls.createSelected);
        if (createSelectedBtn) {
            createSelectedBtn.addEventListener('click', createSelectedCourses);
        }

        // Filter buttons
        container.querySelectorAll(Selectors.filters.buttons).forEach(button => {
            button.addEventListener('click', (e) => {
                const filterType = e.target.dataset.filter;
                applyFilter(filterType);
            });
        });

        // Sort select
        const sortSelect = container.querySelector(Selectors.sorts.select);
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                applySortBy(e.target.value);
            });
        }

        // Select all eligible
        const selectAllEligible = container.querySelector(Selectors.controls.selectAllEligible);
        if (selectAllEligible) {
            selectAllEligible.addEventListener('change', handleSelectAllEligible);
        }

        // Force all
        const forceAll = container.querySelector(Selectors.controls.forceAll);
        if (forceAll) {
            forceAll.addEventListener('change', handleForceAll);
        }

        // Initialize state
        updateCreateSelectedButton();
        updateSelectionCount();
        applyFilter('all');
        applySortBy('status');
    };

    /**
     * Initialize the preview module
     * @param {number} catId Category ID
     * @return {Promise} Promise that resolves when initialization is complete
     */
    const init = async (catId) => {
        categoryId = catId;
        previewContainer = document.querySelector(Selectors.regions.previewContainer);
        if (!previewContainer) {
            console.error('Preview container not found');
            return;
        }

        const previewSection = document.querySelector(Selectors.sections.individualCreation);
        const container = document.querySelector(Selectors.sections.individualContainer);
        
        if (previewSection && container) {
            $(container).on('shown.bs.collapse', async () => {
                if (!cachedPreviewData) {
                    await loadPreviewData(categoryId);
                } else {
                    await renderPreviewContent();
                }
            });
        }

        // Load preview data in the background
        await loadPreviewData(categoryId);
    };

    return {
        init: init
    };
});