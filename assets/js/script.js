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

    }
);