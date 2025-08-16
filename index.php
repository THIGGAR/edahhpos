<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Auntie Eddah POS</title>
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  
</head>
<body>
  <header id="header">
    <div class="logo">
      <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'%3E%3Cpath fill='%23ffcc00' d='M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z'/%3E%3Cpath fill='%23ffffff' d='M12 18c3.31 0 6-2.69 6-6s-2.69-6-6-6-6 2.69-6 6 2.69 6 6 6zm-1-6.5v-3c0-.28.22-.5.5-.5s.5.22.5.5v3h1.5c.28 0 .5.22.5.5s-.22.5-.5.5h-4c-.28 0-.5-.22-.5-.5s.22-.5.5-.5H11z'/%3E%3C/svg%3E" alt="Logo">
      <h1>Auntie Eddah POS</h1>
    </div>
    <nav id="nav">
      <a href="index.php"><i class="fas fa-home"></i> Home</a>
      <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
      <a href="about.php"><i class="fas fa-concierge-bell"></i> Services</a>
      <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
    </nav>
    <button class="mobile-menu-btn" id="menuBtn">
      <i class="fas fa-bars"></i>
    </button>
  </header>

  <section class="hero">
    <div class="hero-content">
      <div class="hero-text">
        <h2>Welcome to Auntie Eddah's Shop!</h2>
        <p>We offer the best quality products at the most affordable prices. Visit us today for all your grocery needs and experience our exceptional customer service.</p>
        <a href="login.php" class="btn"><i class="fas fa-sign-in-alt"></i> Get Started Now</a>
      </div>
      <div class="hero-image">
        <img src="https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80" alt="Auntie Eddah Shop">
      </div>
    </div>
  </section>

  <section class="products-section">
    <div class="section-title">
      <h2>Our Featured Products</h2>
      <p>Discover our top-quality products that customers love</p>
    </div>
    
    <div class="products">
      <div class="product">
        <div class="product-image">
          <img src="https://images.unsplash.com/photo-1598373182133-52452f7691ef?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80" alt="Sugar">
          <div class="product-tag">BEST SELLER</div>
        </div>
        <div class="product-details">
          <h3 class="product-name">Premium Sugar</h3>
          <div class="product-price">MWK4000.99 / kg</div>
          <button class="add-to-cart"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
        </div>
      </div>
      
      <div class="product">
        <div class="product-image">
          <img src="https://images.unsplash.com/photo-1614983650877-86e8b1ab0a1d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80" alt="Cooking Oil">
          <div class="product-tag">NEW</div>
        </div>
        <div class="product-details">
          <h3 class="product-name">Pure Cooking Oil</h3>
          <div class="product-price">MWK8000.99 / liter</div>
          <button class="add-to-cart"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
        </div>
      </div>
      
      <div class="product">
        <div class="product-image">
          <img src="https://images.unsplash.com/photo-1625772299848-391b6a87d7b3?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80" alt="Crunches">
        </div>
        <div class="product-details">
          <h3 class="product-name">Delicious Crunches</h3>
          <div class="product-price">MWK2000.49 / pack</div>
          <button class="add-to-cart"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
        </div>
      </div>
      
      <div class="product">
        <div class="product-image">
          <img src="https://images.unsplash.com/photo-1599490659213-e2b9527bd087?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80" alt="Junkies">
        </div>
        <div class="product-details">
          <h3 class="product-name">Tasty Junkies</h3>
          <div class="product-price">MWK1000.99 / pack</div>
          <button class="add-to-cart"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
        </div>
      </div>
      
      <div class="product">
        <div class="product-image">
          <img src="https://images.unsplash.com/photo-1587132137056-bfbf0166836e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80" alt="Chocolates">
          <div class="product-tag">POPULAR</div>
        </div>
        <div class="product-details">
          <h3 class="product-name">Premium Chocolates</h3>
          <div class="product-price">MWK3000.99 / bar</div>
          <button class="add-to-cart"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
        </div>
      </div>
      
      <div class="product">
        <div class="product-image">
          <img src="https://images.unsplash.com/photo-1606787366850-de6330128bfc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80" alt="Flour">
        </div>
        <div class="product-details">
          <h3 class="product-name">Quality Flour</h3>
          <div class="product-price">MWK3000.49 / kg</div>
          <button class="add-to-cart"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
        </div>
      </div>
    </div>
  </section>

  <section class="about-section">
    <div class="about-content">
      <div class="owner-image">
        <img src="https://images.unsplash.com/photo-1585155770447-2f66e2a397b5?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1738&q=80" alt="Shop Owner">
      </div>
      <div class="owner-info">
        <h3>Meet Auntie Eddah</h3>
        <p>With over 15 years of experience in the retail industry, Auntie Eddah has built a reputation for providing high-quality products with exceptional customer service. Her shop has become a beloved community staple, known for its friendly atmosphere and fair prices.</p>
        <p>"My mission is to provide our community with the best products at the most affordable prices, while creating a shopping experience that feels like visiting family."</p>
        
        <div class="stats">
          <div class="stat-item">
            <div class="number">15+</div>
            <div class="label">Years Experience</div>
          </div>
          <div class="stat-item">
            <div class="number">5000+</div>
            <div class="label">Happy Customers</div>
          </div>
          <div class="stat-item">
            <div class="number">200+</div>
            <div class="label">Quality Products</div>
          </div>
          <div class="stat-item">
            <div class="number">24/7</div>
            <div class="label">Customer Support</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="cta-section">
    <div class="cta-content">
      <h2>Ready to Transform Your Business?</h2>
      <p>Join hundreds of satisfied customers using Auntie Eddah POS to manage their inventory, sales, and customers efficiently.</p>
      <a href="login.php" class="btn"><i class="fas fa-rocket"></i> Get Started Now</a>
    </div>
  </section>

  <footer>
    <div class="footer-content">
      <div class="footer-column">
        <h3>About Us</h3>
        <p>Auntie Eddah POS is a modern point-of-sale system designed for small and medium businesses. Our mission is to simplify your operations and help your business grow.</p>
        <div class="social-links">
          <a href="#"><i class="fab fa-facebook-f"></i></a>
          <a href="#"><i class="fab fa-twitter"></i></a>
          <a href="#"><i class="fab fa-instagram"></i></a>
          <a href="#"><i class="fab fa-linkedin-in"></i></a>
        </div>
      </div>
      
      <div class="footer-column">
        <h3>Quick Links</h3>
        <ul>
          <li><a href="#">Home</a></li>
          <li><a href="#">About Us</a></li>
          <li><a href="#">Services</a></li>
          <li><a href="#">Products</a></li>
          <li><a href="login.php">Login</a></li>
        </ul>
      </div>
      
      <div class="footer-column">
        <h3>Contact Us</h3>
        <div class="contact-info">
          <div class="contact-item">
            <i class="fas fa-map-marker-alt"></i>
            <span>123 Market Street, Lilongwe, Malawi</span>
          </div>
          <div class="contact-item">
            <i class="fas fa-phone-alt"></i>
            <span>+265 888 123 456</span>
          </div>
          <div class="contact-item">
            <i class="fas fa-envelope"></i>
            <span>info@auntieeddah.com</span>
          </div>
          <div class="contact-item">
            <i class="fas fa-clock"></i>
            <span>Mon-Sat: 8:00 AM - 8:00 PM</span>
          </div>
        </div>
      </div>
    </div>
    
    <div class="footer-bottom">
      <p>&copy; 2025 Auntie Eddah Shop. All rights reserved.</p>
    </div>
  </footer>

  <script>
    // Header scroll effect
    const header = document.getElementById('header');
    window.addEventListener('scroll', () => {
      if (window.scrollY > 50) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    });
    
    // Mobile menu toggle
    const menuBtn = document.getElementById('menuBtn');
    const nav = document.getElementById('nav');
    
    menuBtn.addEventListener('click', () => {
      nav.classList.toggle('active');
      const icon = menuBtn.querySelector('i');
      if (nav.classList.contains('active')) {
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-times');
      } else {
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
      }
    });
    
    // Close mobile menu when clicking on links
    const navLinks = document.querySelectorAll('nav a');
    navLinks.forEach(link => {
      link.addEventListener('click', () => {
        nav.classList.remove('active');
        const icon = menuBtn.querySelector('i');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
      });
    });
    
    // Product hover effect
    const products = document.querySelectorAll('.product');
    products.forEach(product => {
      product.addEventListener('mouseenter', () => {
        product.style.transform = 'translateY(-10px)';
      });
      
      product.addEventListener('mouseleave', () => {
        product.style.transform = 'translateY(0)';
      });
    });
    
    // Add to cart buttons
    const addToCartBtns = document.querySelectorAll('.add-to-cart');
    addToCartBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        const product = this.closest('.product');
        const productName = product.querySelector('.product-name').textContent;
        
        // Add animation effect
        this.innerHTML = '<i class="fas fa-check"></i> Added!';
        this.style.backgroundColor = '#4CAF50';
        
        setTimeout(() => {
          this.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
          this.style.backgroundColor = '';
        }, 2000);
        
        console.log(`Added ${productName} to cart`);
      });
    });
  </script>
</body>
</html>