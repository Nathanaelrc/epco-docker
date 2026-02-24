
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Animaciones GSAP globales -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animar elementos con clase fade-in
            gsap.to('.fade-in', {
                opacity: 1,
                y: 0,
                duration: 0.8,
                stagger: 0.2,
                ease: 'power2.out'
            });
            
            // Animar elementos slide-in
            gsap.to('.slide-in-left', {
                opacity: 1,
                x: 0,
                duration: 0.8,
                stagger: 0.15,
                ease: 'power2.out'
            });
            
            gsap.to('.slide-in-right', {
                opacity: 1,
                x: 0,
                duration: 0.8,
                stagger: 0.15,
                ease: 'power2.out'
            });
        });
    </script>
</body>
</html>
