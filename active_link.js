document.addEventListener('DOMContentLoaded', function () {
    // Get current page filename
    const path = window.location.pathname;
    const page = path.split("/").pop();
    const navLinks = document.querySelectorAll('.nav-link');

    // Helper to check if we are on the home page
    const isHomePage = (page === '' || page === 'index.php');

    // 1. Standard Page Highlighting (for non-home pages)
    if (!isHomePage) {
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (!href) return;

            // Extract filename from href
            // Create a temp anchor to resolve relative paths
            const tempLink = document.createElement('a');
            tempLink.href = href;
            const linkPage = tempLink.pathname.split("/").pop();

            if (page === linkPage) {
                link.classList.add('active');
            }
        });
        return; // Don't run scroll spy on other pages
    }

    // 2. Scroll Spy for Home Page
    const sections = document.querySelectorAll('section[id]');

    function scrollSpy() {
        let current = '';
        const scrollPosition = window.scrollY + 150; // Offset for navbar height + buffer

        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;

            if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                current = section.getAttribute('id');
            }
        });

        navLinks.forEach(link => {
            link.classList.remove('active');
            const href = link.getAttribute('href');
            if (href) {
                // Check if href contains the current section hash
                // e.g. href="index.php#jobs" and current="jobs"
                if (current && href.includes('#' + current)) {
                    link.classList.add('active');
                }
            }
        });

        // Special case: If at the very top, highlight Home (hero)
        if (window.scrollY < 50) {
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href').includes('#home')) {
                    link.classList.add('active');
                }
            });
        }
    }

    window.addEventListener('scroll', scrollSpy);
    // Run once on load to set initial state
    scrollSpy();
});
