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


updateCarryForwardField();}
);