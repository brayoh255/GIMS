document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    window.togglePasswordVisibility = function() {
        const passwordInput = document.querySelector('input[name="password"]');
        const toggleIcon = document.querySelector('.toggle-password');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    };
    
    // Add interactive effects to form elements
    const inputs = document.querySelectorAll('.form-group input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.querySelector('.icon').style.color = '#FF6B35';
            this.parentElement.querySelector('.icon').style.transform = 'translateY(-50%) scale(1.1)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.querySelector('.icon').style.color = '#999';
            this.parentElement.querySelector('.icon').style.transform = 'translateY(-50%) scale(1)';
        });
    });
    
    // Animate login button
    const loginBtn = document.querySelector('.login-btn');
    if (loginBtn) {
        loginBtn.addEventListener('mouseenter', function() {
            this.querySelector('i').style.transform = 'translateX(5px)';
            this.style.transform = 'translateY(-2px)';
        });
        
        loginBtn.addEventListener('mouseleave', function() {
            this.querySelector('i').style.transform = 'translateX(0)';
            this.style.transform = 'translateY(0)';
        });
    }
    
    // Create additional floating bubbles
    const bubblesContainer = document.querySelector('.floating-bubbles');
    if (bubblesContainer) {
        for (let i = 0; i < 8; i++) {
            createBubble();
        }
        
        setInterval(createBubble, 3000);
    }
    
    function createBubble() {
        const bubble = document.createElement('div');
        bubble.className = 'bubble';
        
        const size = Math.random() * 100 + 50;
        const posX = Math.random() * 100;
        const posY = Math.random() * 100;
        const duration = Math.random() * 15 + 10;
        const delay = Math.random() * 5;
        
        bubble.style.width = `${size}px`;
        bubble.style.height = `${size}px`;
        bubble.style.left = `${posX}%`;
        bubble.style.top = `${posY}%`;
        bubble.style.animationDuration = `${duration}s`;
        bubble.style.animationDelay = `${delay}s`;
        bubble.style.opacity = Math.random() * 0.2 + 0.05;
        bubble.style.backgroundColor = `rgba(255, 107, 53, ${Math.random() * 0.1 + 0.05})`;
        
        bubblesContainer.appendChild(bubble);
        
        setTimeout(() => {
            bubble.remove();
        }, duration * 1000);
    }
    
    // Add flame animation to logo
    const flameIcon = document.querySelector('.logo i');
    if (flameIcon) {
        setInterval(() => {
            flameIcon.style.animation = 'none';
            void flameIcon.offsetWidth; // Trigger reflow
            flameIcon.style.animation = 'pulse 2s infinite';
        }, 5000);
    }
});