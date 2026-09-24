/*
 * VNDTES Troubleshooting Workspace
 *
 * Handles:
 * - Inspection result popups
 * - Investigation/action result popups
 * - Processing feedback
 * - Toast notifications
 * - Automatic next-step navigation
 * - Troubleshooting workflow guidance
 */

(function () {

    'use strict';


    /* ============================================================
       SERVER-SIDE STATE
    ============================================================ */

    const state =
        window.VNDTES_TROUBLESHOOT || {};


    /* ============================================================
       SAFE HTML ESCAPING
    ============================================================ */

    function escapeHtml(value) {

        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

    }


    /* ============================================================
       GET BOOTSTRAP MODAL
    ============================================================ */

    function getModal(id) {

        const element =
            document.getElementById(id);

        if (
            !element ||
            typeof bootstrap === 'undefined'
        ) {
            return null;
        }

        return bootstrap.Modal
            .getOrCreateInstance(element);

    }


    /* ============================================================
       CLOSE INSPECTION MODAL
    ============================================================ */

    function closeInspectionModal() {

        const element =
            document.getElementById(
                'inspectionModal'
            );

        if (
            !element ||
            typeof bootstrap === 'undefined'
        ) {
            return;
        }

        const modal =
            bootstrap.Modal
                .getInstance(element);

        if (modal) {
            modal.hide();
        }

    }


    /* ============================================================
       SCROLL TO WORKFLOW SECTION
    ============================================================ */

    function scrollToSection(targetId) {

        if (!targetId) {
            return;
        }

        const target =
            document.getElementById(
                targetId
            );

        if (!target) {
            return;
        }

        setTimeout(
            function () {

                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });

            },
            350
        );

    }


    /* ============================================================
       SHOW INSPECTION RESULT
    ============================================================ */

    function showInspection(inspection) {

        if (!inspection) {
            return;
        }

        const title =
            document.getElementById(
                'inspectionModalTitle'
            );

        const body =
            document.getElementById(
                'inspectionModalBody'
            );

        if (!title || !body) {
            return;
        }


        const icon =
            inspection.icon ||
            'bi-search';


        title.innerHTML =
            '<i class="bi ' +
            escapeHtml(icon) +
            ' me-2"></i>' +

            escapeHtml(
                inspection.title ||
                'Inspection Result'
            );


        let html =
            '<div class="row g-3">';


        Object.entries(
            inspection.data || {}
        ).forEach(
            function ([label, value]) {

                html +=

                    '<div class="col-md-6">' +

                        '<div class="inspection-item">' +

                            '<div class="inspection-label">' +
                                escapeHtml(label) +
                            '</div>' +

                            '<div class="inspection-value">' +
                                escapeHtml(value) +
                            '</div>' +

                        '</div>' +

                    '</div>';

            }
        );


        html +=
            '</div>';


        /*
         * NEXT STEP FOR INSPECTION
         */

        const nextText =
            String(
                inspection.next || ''
            );


        if (nextText) {

            html +=

                '<div class="alert alert-primary mt-4 mb-0">' +

                    '<strong>Next step:</strong> ' +

                    escapeHtml(nextText) +

                '</div>';

        }


        body.innerHTML =
            html;


        const modal =
            getModal(
                'inspectionModal'
            );


        if (modal) {
            modal.show();
        }

    }


    /* ============================================================
       DETERMINE NEXT WORKFLOW STAGE
       ============================================================ */

    function determineNextStep(result) {

        if (!result) {
            return null;
        }


        /*
         * If PHP provides an explicit target,
         * always respect it.
         */

        if (result.nextTarget) {

            return {

                target:
                    result.nextTarget,

                label:
                    result.nextLabel ||
                    'Continue'

            };

        }


        if (result.next_section) {

            return {

                target:
                    result.next_section,

                label:
                    result.next_label ||
                    'Continue'

            };

        }


        const nextText =
            String(
                result.next || ''
            ).toLowerCase();


        const resultTitle =
            String(
                result.title || ''
            ).toLowerCase();


        const resultType =
            String(
                result.type ||
                result.resultType ||
                ''
            ).toLowerCase();


        /*
         * --------------------------------------------------------
         * INVESTIGATION → DIAGNOSIS
         * --------------------------------------------------------
         */

        if (

            resultType ===
            'investigation' ||

            resultType ===
            'inspection' ||

            nextText.includes(
                'diagnosis'
            ) ||

            resultTitle.includes(
                'ip configuration'
            ) ||

            resultTitle.includes(
                'interface status'
            ) ||

            resultTitle.includes(
                'connectivity'
            ) ||

            resultTitle.includes(
                'routing'
            ) ||

            resultTitle.includes(
                'vlan'
            ) ||

            resultTitle.includes(
                'dhcp'
            ) ||

            resultTitle.includes(
                'wireless'
            ) ||

            resultTitle.includes(
                'device configuration'
            )

        ) {

            return {

                target:
                    'diagnosisSection',

                label:
                    'Continue to Diagnosis'

            };

        }


        /*
         * --------------------------------------------------------
         * DIAGNOSIS → CORRECTION
         * --------------------------------------------------------
         */

        if (

            resultType ===
            'diagnosis' ||

            nextText.includes(
                'apply correction'
            ) ||

            nextText.includes(
                'proceed to apply'
            ) ||

            resultTitle.includes(
                'diagnosis accepted'
            ) ||

            resultTitle.includes(
                'diagnosis correct'
            )

        ) {

            return {

                target:
                    'diagnosisSection',

                label:
                    'Continue to Correction'

            };

        }


        /*
         * --------------------------------------------------------
         * CORRECTION → VERIFICATION
         * --------------------------------------------------------
         */

        if (

            resultType ===
            'correction' ||

            nextText.includes(
                'verify solution'
            ) ||

            nextText.includes(
                'run verify'
            ) ||

            resultTitle.includes(
                'correction applied'
            ) ||

            resultTitle.includes(
                'correction successful'
            )

        ) {

            return {

                target:
                    'diagnosisSection',

                label:
                    'Continue to Verification'

            };

        }


        /*
         * --------------------------------------------------------
         * VERIFICATION → SUBMISSION
         * --------------------------------------------------------
         */

        if (

            resultType ===
            'verification' ||

            nextText.includes(
                'submit'
            ) ||

            nextText.includes(
                'submission'
            ) ||

            resultTitle.includes(
                'solution verified'
            ) ||

            resultTitle.includes(
                'verification successful'
            )

        ) {

            return {

                target:
                    'diagnosisSection',

                label:
                    'Continue to Submission'

            };

        }


        return null;

    }


    /* ============================================================
       SHOW ACTION RESULT
    ============================================================ */

    function showActionResult(result) {

        if (!result) {
            return;
        }


        const title =
            document.getElementById(
                'inspectionModalTitle'
            );

        const body =
            document.getElementById(
                'inspectionModalBody'
            );


        if (!title || !body) {
            return;
        }


        const icon =
            result.icon ||
            'bi-tools';


        title.innerHTML =

            '<i class="bi ' +

            escapeHtml(icon) +

            ' me-2"></i>' +

            escapeHtml(
                result.title ||
                'Troubleshooting Result'
            );


        let html = '';


        /*
         * --------------------------------------------------------
         * RESULT
         * --------------------------------------------------------
         */

        html +=

            '<div class="mb-3">' +

                '<div class="fw-semibold mb-2">' +
                    'Result' +
                '</div>' +

                '<div class="alert alert-light border mb-0">' +

                    escapeHtml(
                        result.result || ''
                    ) +

                '</div>' +

            '</div>';


        /*
         * --------------------------------------------------------
         * FINDINGS
         * --------------------------------------------------------
         */

        if (

            Array.isArray(
                result.findings
            ) &&

            result.findings.length

        ) {

            html +=

                '<div class="mb-3">' +

                    '<div class="fw-semibold mb-2">' +
                        'Findings' +
                    '</div>' +

                    '<ul class="list-group">';


            result.findings.forEach(
                function (finding) {

                    html +=

                        '<li class="list-group-item">' +

                            escapeHtml(
                                finding
                            ) +

                        '</li>';

                }
            );


            html +=

                    '</ul>' +

                '</div>';

        }


        /*
         * --------------------------------------------------------
         * NEXT STEP INFORMATION
         * --------------------------------------------------------
         */

        if (result.next) {

            html +=

                '<div class="alert alert-primary mb-3">' +

                    '<strong>Next step:</strong> ' +

                    escapeHtml(
                        result.next
                    ) +

                '</div>';

        }


        /*
         * --------------------------------------------------------
         * CONTINUE BUTTON
         * --------------------------------------------------------
         */

        const nextStep =
            determineNextStep(
                result
            );


        if (nextStep) {

            html +=

                '<div class="d-grid mt-3">' +

                    '<button ' +

                        'type="button" ' +

                        'class="btn btn-primary btn-lg js-next-step" ' +

                        'data-target="' +
                        escapeHtml(
                            nextStep.target
                        ) +
                        '">' +

                        '<i class="bi bi-arrow-right-circle me-2"></i>' +

                        escapeHtml(
                            nextStep.label
                        ) +

                    '</button>' +

                '</div>';

        }


        body.innerHTML =
            html;


        const modal =
            getModal(
                'inspectionModal'
            );


        if (modal) {
            modal.show();
        }

    }


    /* ============================================================
       SHOW TOAST
    ============================================================ */

    function showToast(
        message,
        type
    ) {

        if (!message) {
            return;
        }


        let container =
            document.getElementById(
                'vnDtesToastContainer'
            );


        if (!container) {

            container =
                document.createElement(
                    'div'
                );


            container.id =
                'vnDtesToastContainer';


            container.className =
                'toast-container position-fixed top-0 end-0 p-3';


            container.style.zIndex =
                '1085';


            document.body.appendChild(
                container
            );

        }


        const allowedTypes = [

            'success',

            'danger',

            'warning',

            'info'

        ];


        const safeType =
            allowedTypes.includes(type)
                ? type
                : 'info';


        const toast =
            document.createElement(
                'div'
            );


        toast.className =
            'toast align-items-center text-bg-' +
            safeType +
            ' border-0';


        toast.setAttribute(
            'role',
            'alert'
        );


        toast.setAttribute(
            'aria-live',
            'assertive'
        );


        toast.setAttribute(
            'aria-atomic',
            'true'
        );


        toast.innerHTML =

            '<div class="d-flex">' +

                '<div class="toast-body">' +

                    escapeHtml(
                        message
                    ) +

                '</div>' +

                '<button ' +

                    'type="button" ' +

                    'class="btn-close btn-close-white me-2 m-auto" ' +

                    'data-bs-dismiss="toast">' +

                '</button>' +

            '</div>';


        container.appendChild(
            toast
        );


        if (
            typeof bootstrap !==
            'undefined'
        ) {

            const instance =
                new bootstrap.Toast(
                    toast,
                    {
                        delay: 4500
                    }
                );


            instance.show();


            toast.addEventListener(
                'hidden.bs.toast',
                function () {

                    toast.remove();

                }
            );

        }

    }


    /* ============================================================
       SHOW PROCESSING MODAL
    ============================================================ */

    function showProcessing(text) {

        const modalElement =
            document.getElementById(
                'processingModal'
            );


        if (

            !modalElement ||

            typeof bootstrap ===
            'undefined'

        ) {

            return;

        }


        const label =
            modalElement.querySelector(
                '.fw-semibold'
            );


        const sub =
            modalElement.querySelector(
                '.small.text-muted'
            );


        if (label) {

            label.textContent =
                text ||
                'Processing troubleshooting action...';

        }


        if (sub) {

            sub.textContent =
                'Recording the action and preparing the next step.';

        }


        bootstrap.Modal
            .getOrCreateInstance(
                modalElement
            )
            .show();

    }


    /* ============================================================
       HANDLE FORM SUBMISSION
    ============================================================ */

    function handleFormSubmission(form) {

        const actionInput =
            form.querySelector(
                'input[name="action_type"]'
            );


        const actionType =
            actionInput
                ? actionInput.value
                : '';


        /*
         * INSPECTIONS
         */

        if (

            actionType ===
            'inspect_device' ||

            actionType ===
            'inspect_interface' ||

            actionType ===
            'inspect_connection'

        ) {

            showProcessing(
                'Inspecting network element...'
            );

            return;

        }


        /*
         * DIAGNOSIS
         */

        if (

            actionType ===
            'diagnose_fault' ||

            actionType ===
            'submit_diagnosis'

        ) {

            showProcessing(
                'Evaluating your diagnosis...'
            );

            return;

        }


        /*
         * CORRECTION
         */

        if (

            actionType ===
            'apply_correction' ||

            actionType ===
            'apply_fix'

        ) {

            showProcessing(
                'Applying troubleshooting correction...'
            );

            return;

        }


        /*
         * VERIFICATION
         */

        if (

            actionType ===
            'verify_solution' ||

            actionType ===
            'verify_fix'

        ) {

            showProcessing(
                'Verifying the network solution...'
            );

            return;

        }


        /*
         * FINAL SUBMISSION
         */

        if (
            actionType ===
            'submit_attempt'
        ) {

            showProcessing(
                'Submitting troubleshooting attempt...'
            );

            return;

        }


        /*
         * OTHER WORKSPACE ACTION
         */

        showProcessing(
            'Processing troubleshooting action...'
        );

    }


    /* ============================================================
       DOM READY
    ============================================================ */

    document.addEventListener(
        'DOMContentLoaded',
        function () {


            /* ====================================================
               FORM HANDLERS
            ==================================================== */

            document
                .querySelectorAll(
                    '.js-inspection-form, .js-workspace-form'
                )
                .forEach(
                    function (form) {

                        form.addEventListener(
                            'submit',
                            function () {

                                handleFormSubmission(
                                    form
                                );

                            }
                        );

                    }
                );


            /* ====================================================
               NEXT STEP BUTTON
            ==================================================== */

            document.addEventListener(
                'click',
                function (event) {

                    const button =
                        event.target.closest(
                            '.js-next-step'
                        );


                    if (!button) {
                        return;
                    }


                    const targetId =
                        button.dataset.target;


                    /*
                     * Close popup first
                     */

                    closeInspectionModal();


                    /*
                     * Move to next workflow section
                     */

                    scrollToSection(
                        targetId
                    );

                }
            );


            /* ====================================================
               SERVER MESSAGE
            ==================================================== */

            if (state.message) {

                showToast(
                    state.message,
                    state.messageType ||
                    'info'
                );

            }


            /* ====================================================
               INSPECTION RESULT
            ==================================================== */

            if (state.inspection) {

                showInspection(
                    state.inspection
                );

            }


            /* ====================================================
               ACTION RESULT
            ==================================================== */

            else if (
                state.actionResult
            ) {

                showActionResult(
                    state.actionResult
                );

            }


            /* ====================================================
               VERIFICATION RESULT
            ==================================================== */

            if (
                state.verificationResult &&
                state.verificationResult.verified
            ) {

                scrollToSection(
                    'diagnosisSection'
                );

            }


            /* ====================================================
               INVESTIGATION CARD EFFECT
            ==================================================== */

            document
                .querySelectorAll(
                    '.investigation-action-card'
                )
                .forEach(
                    function (card) {

                        card.addEventListener(
                            'mouseenter',
                            function () {

                                card.classList.add(
                                    'shadow-sm'
                                );

                            }
                        );


                        card.addEventListener(
                            'mouseleave',
                            function () {

                                card.classList.remove(
                                    'shadow-sm'
                                );

                            }
                        );

                    }
                );


            /* ====================================================
               PREVENT DOUBLE CLICK ON SUBMIT
            ==================================================== */

            document
                .querySelectorAll(
                    '.js-workspace-form button[type="submit"], ' +
                    '.js-inspection-form button[type="submit"]'
                )
                .forEach(
                    function (button) {

                        const form =
                            button.closest(
                                'form'
                            );


                        if (!form) {
                            return;
                        }


                        form.addEventListener(
                            'submit',
                            function () {

                                setTimeout(
                                    function () {

                                        button.disabled =
                                            true;

                                    },
                                    10
                                );

                            }
                        );

                    }
                );

        }
    );

})();