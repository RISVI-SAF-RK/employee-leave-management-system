document.addEventListener(
    'DOMContentLoaded',
    function () {

        const sidebar =
            document.getElementById(
                'adminSidebar'
            );

        const overlay =
            document.getElementById(
                'sidebarOverlay'
            );

        const menuButton =
            document.getElementById(
                'mobileMenuButton'
            );

        const closeButton =
            document.getElementById(
                'sidebarClose'
            );

        function openSidebar() {

            if (!sidebar || !overlay) {
                return;
            }

            sidebar.classList.add('open');

            overlay.classList.add('show');

            document.body.style.overflow =
                'hidden';
        }


        function closeSidebar() {

            if (!sidebar || !overlay) {
                return;
            }

            sidebar.classList.remove('open');

            overlay.classList.remove('show');

            document.body.style.overflow =
                '';
        }


        if (menuButton) {

            menuButton.addEventListener(
                'click',
                openSidebar
            );

        }


        if (closeButton) {

            closeButton.addEventListener(
                'click',
                closeSidebar
            );

        }


        if (overlay) {

            overlay.addEventListener(
                'click',
                closeSidebar
            );

        }


        window.addEventListener(
            'resize',
            function () {

                if (
                    window.innerWidth >= 992
                ) {
                    closeSidebar();
                }

            }
        );

    
        /*
        ==========================================================
        Employee / Manager Role Field
        ==========================================================
        */

        const roleSelect =
            document.getElementById(
                'roleSelect'
            );

        const managerField =
            document.getElementById(
                'managerField'
            );

        const managerSelect =
            document.getElementById(
                'managerSelect'
            );


        function updateManagerField() {

            if (
                !roleSelect ||
                !managerField
            ) {
                return;
            }

            const selectedOption =
                roleSelect.options[
                    roleSelect.selectedIndex
                ];

            const roleName =
                selectedOption
                    ? selectedOption.dataset.role
                    : '';


            if (roleName === 'Employee') {

                managerField.style.display = '';

                if (managerSelect) {
                    managerSelect.required = true;
                }

            } else {

                managerField.style.display = 'none';

                if (managerSelect) {

                    managerSelect.required = false;

                    managerSelect.value = '';
                }

            }

        }


        if (roleSelect) {

            updateManagerField();

            roleSelect.addEventListener(
                'change',
                updateManagerField
            );

        }
    
/*
==========================================================
Leave Policy Carry Forward
==========================================================
*/

const carryForwardRadios =
    document.querySelectorAll(
        'input[name="carry_forward_allowed"]'
    );

const carryForwardLimit =
    document.getElementById(
        'carryForwardLimit'
    );

const maxCarryForwardInput =
    document.getElementById(
        'max_carry_forward_days'
    );


function updateCarryForwardField() {

    if (
        !carryForwardLimit ||
        carryForwardRadios.length === 0
    ) {
        return;
    }


    const selected =
        document.querySelector(
            'input[name="carry_forward_allowed"]:checked'
        );


    const carryForwardEnabled =
        selected !== null
        &&
        selected.value === '1';


    if (carryForwardEnabled) {

        carryForwardLimit.style.display =
            '';

        if (maxCarryForwardInput) {

            maxCarryForwardInput.required =
                true;
        }

    } else {

        carryForwardLimit.style.display =
            'none';


        if (maxCarryForwardInput) {

            maxCarryForwardInput.required =
                false;

            maxCarryForwardInput.value =
                '0';
        }

    }
}


carryForwardRadios.forEach(
    function (radio) {

        radio.addEventListener(
            'change',
            updateCarryForwardField
        );

    }
);


updateCarryForwardField();

/*
==========================================================
EMPLOYEE LEAVE APPLICATION
==========================================================
*/

const leaveTypeSelect =
    document.getElementById(
        'leave_type_id'
    );

const policyPreview =
    document.getElementById(
        'leavePolicyPreview'
    );

const previewAvailable =
    document.getElementById(
        'previewAvailable'
    );

const previewPending =
    document.getElementById(
        'previewPending'
    );

const previewService =
    document.getElementById(
        'previewService'
    );

const previewAttachment =
    document.getElementById(
        'previewAttachment'
    );

const attachmentInput =
    document.getElementById(
        'attachment'
    );

const attachmentRequiredMark =
    document.getElementById(
        'attachmentRequiredMark'
    );

const startDateInput =
    document.getElementById(
        'start_date'
    );

const endDateInput =
    document.getElementById(
        'end_date'
    );

const requestedDays =
    document.getElementById(
        'requestedDays'
    );


function updateLeaveTypePreview() {

    if (
        !leaveTypeSelect ||
        !policyPreview
    ) {
        return;
    }


    const option =
        leaveTypeSelect.options[
            leaveTypeSelect.selectedIndex
        ];


    if (
        !option ||
        !option.value
    ) {

        policyPreview.classList.add(
            'd-none'
        );

        if (attachmentInput) {
            attachmentInput.required = false;
        }

        if (attachmentRequiredMark) {
            attachmentRequiredMark
                .classList.add(
                    'd-none'
                );
        }

        return;
    }


    policyPreview.classList.remove(
        'd-none'
    );


    if (previewAvailable) {

        previewAvailable.textContent =
            option.dataset.available
            || '0.00';
    }


    if (previewPending) {

        previewPending.textContent =
            option.dataset.pending
            || '0.00';
    }


    if (previewService) {

        previewService.textContent =
            option.dataset.minService
            || '0';
    }


    const requiresAttachment =
        option.dataset.attachment
        === '1';


    if (previewAttachment) {

        previewAttachment.textContent =
            requiresAttachment
                ? 'Required'
                : 'Optional';
    }


    if (attachmentInput) {

        attachmentInput.required =
            requiresAttachment;
    }


    if (attachmentRequiredMark) {

        attachmentRequiredMark
            .classList.toggle(
                'd-none',
                !requiresAttachment
            );
    }
}


function updateRequestedDays() {

    if (
        !startDateInput ||
        !endDateInput ||
        !requestedDays
    ) {
        return;
    }


    const start =
        startDateInput.value;

    const end =
        endDateInput.value;


    if (!start || !end) {

        requestedDays.textContent =
            'Select dates';

        return;
    }


    const startDate =
        new Date(
            start + 'T00:00:00'
        );

    const endDate =
        new Date(
            end + 'T00:00:00'
        );


    if (
        endDate < startDate
    ) {

        requestedDays.textContent =
            'Invalid date range';

        return;
    }


    if (
        startDate.getFullYear()
        !==
        endDate.getFullYear()
    ) {

        requestedDays.textContent =
            'Separate leave years required';

        return;
    }


    const millisecondsPerDay =
        1000
        * 60
        * 60
        * 24;


    const difference =
        Math.round(
            (
                endDate
                -
                startDate
            )
            /
            millisecondsPerDay
        )
        + 1;


    requestedDays.textContent =
        difference
        + (
            difference === 1
                ? ' day'
                : ' days'
        );


    /*
    Keep End Date >= Start Date
    */

    if (endDateInput) {

        endDateInput.min =
            start;
    }
}


if (leaveTypeSelect) {

    leaveTypeSelect.addEventListener(
        'change',
        updateLeaveTypePreview
    );

    updateLeaveTypePreview();
}


if (startDateInput) {

    startDateInput.addEventListener(
        'change',
        updateRequestedDays
    );
}


if (endDateInput) {

    endDateInput.addEventListener(
        'change',
        updateRequestedDays
    );
}


updateRequestedDays();}
);