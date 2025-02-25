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
            createSelected: '.create-selected'
        },
        regions: {
            previewGrid: '.course-grid',
            courseCard: '.course-preview-card',
            previewContainer: '#evento-preview-content'
        }
    };

    let cachedPreviewData = null;
    let previewContainer = null;
    let categoryId = null;

    const formatDate = (timestamp) => {
        return new Date(timestamp * 1000).toLocaleString();
    };

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
                courses: cachedPreviewData.courses.map(course => ({
                    ...course,
                    formattedStartDate: formatDate(course.startdate),
                    formattedEndDate: formatDate(course.enddate),
                    subcourses: Array.isArray(course.subcourses) ? course.subcourses : []
                })),
                showCreateSelected: true,
                hasSelectedCourses: cachedPreviewData.courses.some(course => course.canCreate)
            };

            const template = await Templates.render('local_eventocoursecreation/preview_grid', templateContext);
            previewContainer.innerHTML = template;

            // Initialize handlers for newly rendered content
            initializePreviewHandlers();

        } catch (error) {
            console.error('Error rendering preview content:', error);
            console.error('Response data:', cachedPreviewData);
            previewContainer.innerHTML = `
                <div class="alert alert-danger">
                    ${M.util.get_string('previewerror', 'local_eventocoursecreation')}
                </div>`;
            Notification.exception(error);
        }
    };

    const loadPreviewInBackground = async (catId) => {
        try {
            const formData = new FormData();
            formData.append('categoryid', catId);
            formData.append('sesskey', M.cfg.sesskey);
            
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
            console.error('Background preview load failed:', error);
            if (previewContainer) {
                previewContainer.innerHTML = `
                    <div class="alert alert-danger">
                        ${error.message || M.util.get_string('previewloadfailed', 'local_eventocoursecreation')}
                    </div>`;
            }
            return null;
        }
    };

    const createCourse = async (eventId, force, button) => {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
        button.disabled = true;

        try {
            const formData = new FormData();
            formData.append('eventid', eventId);
            formData.append('force', force ? 1 : 0);
            formData.append('categoryid', categoryId);
            formData.append('sesskey', M.cfg.sesskey);
            formData.append('cachedEvents', JSON.stringify(cachedPreviewData.courses));
            
            const response = await fetch(`${M.cfg.wwwroot}/local/eventocoursecreation/create_course.php`, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (!result.status) {
                throw new Error(result.message || M.util.get_string('creationfailed', 'local_eventocoursecreation'));
            }

            const card = button.closest(Selectors.regions.courseCard);
            if (card) {
                // Remove the course from cached data
                cachedPreviewData.courses = cachedPreviewData.courses.filter(
                    course => course.eventId !== parseInt(eventId)
                );
                card.remove();
                updateCreateSelectedButton();
            }
            
            Notification.addNotification({
                message: result.message || M.util.get_string('creationsuccessful', 'local_eventocoursecreation'),
                type: 'success'
            });

        } catch (error) {
            button.innerHTML = originalText;
            button.disabled = false;
            Notification.exception(error);
        }
    };

    const createMultipleCourses = async (courses, button) => {
        const originalText = button.innerHTML;
        button.innerHTML = `<i class="fa fa-spinner fa-spin"></i> ${M.util.get_string('creating', 'local_eventocoursecreation')}`;
        button.disabled = true;

        const results = [];
        for (const course of courses) {
            const formData = new FormData();
            formData.append('eventid', course.eventId);
            formData.append('force', course.force ? 1 : 0);
            formData.append('categoryid', categoryId);
            formData.append('sesskey', M.cfg.sesskey);
            formData.append('cachedEvents', JSON.stringify(cachedPreviewData.courses));

            try {
                const response = await fetch(`${M.cfg.wwwroot}/local/eventocoursecreation/create_course.php`, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                results.push({ status: result.status, message: result.message });
                
                if (result.status) {
                    // Remove created course from cached data
                    cachedPreviewData.courses = cachedPreviewData.courses.filter(
                        c => c.eventId !== course.eventId
                    );
                }
            } catch (error) {
                results.push({ status: false, message: error.message });
            }
        }

        const successful = results.filter(r => r.status).length;
        const failed = results.length - successful;

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

        // Re-render the preview content to reflect changes
        await renderPreviewContent();
    };

    const updateCourseSelectionState = (courseCard) => {
        const checkbox = courseCard.querySelector(Selectors.controls.courseSelect);
        const forceCheckbox = courseCard.querySelector(Selectors.controls.individualForce);
        const createButton = courseCard.querySelector(Selectors.controls.createSingle);
        const isForced = forceCheckbox?.checked || false;
        const eventId = courseCard.dataset.eventId;
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

    const updateCreateSelectedButton = () => {
        const createSelectedBtn = document.querySelector(Selectors.controls.createSelected);
        if (!createSelectedBtn) return;

        const selectedCourses = document.querySelectorAll(`${Selectors.controls.courseSelect}:checked`);
        createSelectedBtn.disabled = selectedCourses.length === 0;
    };

    const handleForceChange = (e) => {
        const courseCard = e.target.closest(Selectors.regions.courseCard);
        if (courseCard) {
            updateCourseSelectionState(courseCard);
        }
    };

    const handleCourseSelection = (e) => {
        const courseCard = e.target.closest(Selectors.regions.courseCard);
        if (courseCard) {
            updateCourseSelectionState(courseCard);
        }
    };

    const createSelectedCourses = async () => {
        const selectedCards = document.querySelectorAll(`${Selectors.controls.courseSelect}:checked`);
        const courses = Array.from(selectedCards).map(checkbox => {
            const card = checkbox.closest(Selectors.regions.courseCard);
            const forceCheckbox = card.querySelector(Selectors.controls.individualForce);
            return {
                eventId: parseInt(card.dataset.eventId),
                force: forceCheckbox.checked
            };
        });

        if (courses.length === 0) {
            return;
        }

        const createSelectedBtn = document.querySelector(Selectors.controls.createSelected);
        await createMultipleCourses(courses, createSelectedBtn);
    };

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
                const card = e.target.closest(Selectors.regions.courseCard);
                const eventId = parseInt(card.dataset.eventId);
                const forceCheckbox = card.querySelector(Selectors.controls.individualForce);
                await createCourse(eventId, forceCheckbox.checked, e.target);
            });
        });

        // Create selected button
        const createSelectedBtn = container.querySelector(Selectors.controls.createSelected);
        if (createSelectedBtn) {
            createSelectedBtn.addEventListener('click', createSelectedCourses);
        }

        // Initial states
        document.querySelectorAll(Selectors.regions.courseCard).forEach(card => {
            updateCourseSelectionState(card);
        });
    };

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
                    await loadPreviewInBackground(categoryId);
                } else {
                    await renderPreviewContent();
                }
            });
        }

        await loadPreviewInBackground(categoryId);
    };

    return {
        init: init
    };
});