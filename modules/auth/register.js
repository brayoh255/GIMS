document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility for both password fields
    window.togglePasswordVisibility = function(icon) {
        const input = icon.parentElement.querySelector('input');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    };
    
    // Add interactive effects to form elements
    const inputs = document.querySelectorAll('.form-group input, .form-group select');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            const icon = this.parentElement.querySelector('.icon');
            if (icon) {
                icon.style.color = '#FF6B35';
                icon.style.transform = 'translateY(-50%) scale(1.1)';
            }
        });
        
        input.addEventListener('blur', function() {
            const icon = this.parentElement.querySelector('.icon');
            if (icon) {
                icon.style.color = '#999';
                icon.style.transform = 'translateY(-50%) scale(1)';
            }
        });
    });
    
    // Animate register button
    const registerBtn = document.querySelector('.register-btn');
    if (registerBtn) {
        registerBtn.addEventListener('mouseenter', function() {
            this.querySelector('i').style.transform = 'scale(1.2)';
            this.style.transform = 'translateY(-2px)';
        });
        
        registerBtn.addEventListener('mouseleave', function() {
            this.querySelector('i').style.transform = 'scale(1)';
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