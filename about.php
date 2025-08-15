<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Auntie Eddah POS - About & Services</title>
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  
  <style>
    :root {
      --primary-color: #4a6baf;
      --secondary-color: #ff6b6b;
      --accent-color: #ffcc00;
      --dark-color: #003366;
      --light-color: #f0f4f8;
      --white: #ffffff;
      --text-color: #333333;
      --footer-bg: #1a2a4f;
      --card-bg: #ffffff;
      --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
      --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      --shadow-hover: 0 15px 40px rgba(0, 0, 0, 0.15);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Nunito', sans-serif;
    }

    body {
      background: linear-gradient(135deg, #f0f4f8 0%, #e6eef7 100%);
      color: var(--text-color);
      line-height: 1.6;
      overflow-x: hidden;
    }

    /* Header Styles */
    header {
      background: var(--dark-color);
      color: var(--white);
      padding: 15px 5%;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
      transition: var(--transition);
    }

    .scrolled {
      padding: 10px 5%;
      background: rgba(0, 51, 102, 0.95);
      backdrop-filter: blur(5px);
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
      transition: var(--transition);
    }

    .logo img {
      height: 45px;
      transition: var(--transition);
    }

    .logo h1 {
      font-size: 1.8rem;
      background: linear-gradient(to right, var(--white), var(--accent-color));
      -webkit-background-clip: text;
      color: transparent;
      font-weight: 800;
      letter-spacing: -0.5px;
    }

    nav {
      display: flex;
      gap: 25px;
    }

    nav a {
      color: var(--white);
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
      position: relative;
      padding: 8px 0;
    }

    nav a:hover {
      color: var(--accent-color);
    }

    nav a::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 0;
      height: 3px;
      background: var(--accent-color);
      border-radius: 3px;
      transition: var(--transition);
    }

    nav a:hover::after {
      width: 100%;
    }

    .mobile-menu-btn {
      display: none;
      background: transparent;
      border: none;
      color: var(--white);
      font-size: 1.8rem;
      cursor: pointer;
    }

    /* Page Container */
    .page-container {
      max-width: 1200px;
      margin: 120px auto 60px;
      padding: 0 5%;
    }

    /* Page Title */
    .page-title {
      text-align: center;
      margin-bottom: 60px;
    }

    .page-title h1 {
      font-size: 3rem;
      color: var(--dark-color);
      margin-bottom: 15px;
      position: relative;
      display: inline-block;
    }

    .page-title h1::after {
      content: '';
      position: absolute;
      bottom: -15px;
      left: 50%;
      transform: translateX(-50%);
      width: 100px;
      height: 5px;
      background: var(--accent-color);
      border-radius: 3px;
    }

    .page-title p {
      max-width: 700px;
      margin: 30px auto 0;
      font-size: 1.2rem;
      color: var(--primary-color);
    }

    /* About Section */
    .about-section {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 50px;
      margin-bottom: 80px;
      align-items: center;
    }

    .about-image {
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow);
      transition: var(--transition);
    }

    .about-image img {
      width: 100%;
      display: block;
      transition: var(--transition);
    }

    .about-image:hover {
      transform: translateY(-10px);
    }

    .about-image:hover img {
      transform: scale(1.05);
    }

    .about-content h2 {
      font-size: 2.2rem;
      color: var(--dark-color);
      margin-bottom: 25px;
    }

    .about-content p {
      margin-bottom: 20px;
      font-size: 1.1rem;
      line-height: 1.8;
    }

    .values {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 30px;
      margin-top: 40px;
    }

    .value-card {
      background: var(--white);
      border-radius: 15px;
      padding: 30px;
      box-shadow: var(--shadow);
      transition: var(--transition);
      text-align: center;
    }

    .value-card:hover {
      transform: translateY(-10px);
      box-shadow: var(--shadow-hover);
    }

    .value-icon {
      width: 70px;
      height: 70px;
      background: var(--light-color);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      font-size: 1.8rem;
      color: var(--primary-color);
    }

    .value-card h3 {
      margin-bottom: 15px;
      color: var(--dark-color);
    }

    /* Team Section */
    .team-section {
      margin-bottom: 80px;
    }

    .section-title {
      text-align: center;
      margin-bottom: 60px;
    }

    .section-title h2 {
      font-size: 2.5rem;
      color: var(--dark-color);
      margin-bottom: 15px;
      position: relative;
      display: inline-block;
    }

    .section-title h2::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 80px;
      height: 4px;
      background: var(--accent-color);
      border-radius: 2px;
    }

    .team-members {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 30px;
    }

    .team-member {
      background: var(--white);
      border-radius: 15px;
      overflow: hidden;
      box-shadow: var(--shadow);
      transition: var(--transition);
      text-align: center;
    }

    .team-member:hover {
      transform: translateY(-10px);
      box-shadow: var(--shadow-hover);
    }

    .member-image {
      height: 250px;
      overflow: hidden;
    }

    .member-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: var(--transition);
    }

    .team-member:hover .member-image img {
      transform: scale(1.1);
    }

    .member-info {
      padding: 25px;
    }

    .member-info h3 {
      margin-bottom: 5px;
      color: var(--dark-color);
    }

    .member-info .position {
      color: var(--primary-color);
      font-weight: 600;
      margin-bottom: 15px;
    }

    .social-links {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-top: 15px;
    }

    .social-links a {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      background: var(--light-color);
      color: var(--primary-color);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: var(--transition);
    }

    .social-links a:hover {
      background: var(--primary-color);
      color: var(--white);
    }

    /* Services Section */
    .services-section {
      background: linear-gradient(to bottom right, #003366, #1a2a4f);
      color: var(--white);
      padding: 80px 5%;
      border-radius: 20px;
      margin-bottom: 80px;
      position: relative;
      overflow: hidden;
    }

    .services-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffcc00' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
    }

    .services-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 30px;
      position: relative;
      z-index: 2;
    }

    .service-card {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 15px;
      padding: 40px 30px;
      backdrop-filter: blur(5px);
      transition: var(--transition);
      text-align: center;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .service-card:hover {
      transform: translateY(-10px);
      background: rgba(255, 255, 255, 0.15);
    }

    .service-icon {
      width: 80px;
      height: 80px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 25px;
      font-size: 2rem;
      color: var(--accent-color);
    }

    .service-card h3 {
      font-size: 1.5rem;
      margin-bottom: 20px;
      color: var(--white);
    }

    .service-card p {
      margin-bottom: 25px;
      opacity: 0.9;
    }

    .service-card .btn {
      display: inline-block;
      padding: 12px 30px;
      background: var(--accent-color);
      color: var(--dark-color);
      text-decoration: none;
      border-radius: 30px;
      font-weight: 700;
      transition: var(--transition);
      border: none;
      cursor: pointer;
    }

    .service-card .btn:hover {
      background: var(--white);
      transform: scale(1.05);
    }

    /* Testimonials */
    .testimonials-section {
      margin-bottom: 80px;
    }

    .testimonials {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 30px;
    }

    .testimonial {
      background: var(--white);
      border-radius: 15px;
      padding: 30px;
      box-shadow: var(--shadow);
      position: relative;
      transition: var(--transition);
    }

    .testimonial:hover {
      transform: translateY(-10px);
      box-shadow: var(--shadow-hover);
    }

    .testimonial::before {
      content: '"';
      position: absolute;
      top: 20px;
      left: 20px;
      font-size: 5rem;
      color: var(--light-color);
      font-family: Georgia, serif;
      line-height: 1;
    }

    .testimonial-content {
      position: relative;
      z-index: 1;
      padding-top: 20px;
    }

    .testimonial-content p {
      margin-bottom: 25px;
      font-style: italic;
      font-size: 1.1rem;
    }

    .client {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .client img {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      object-fit: cover;
    }

    .client-info h4 {
      margin-bottom: 5px;
    }

    .client-info .position {
      color: var(--primary-color);
      font-size: 0.9rem;
    }

    /* CTA Section */
    .cta-section {
      text-align: center;
      padding: 80px 5%;
      background: linear-gradient(to right, #ff6b6b, #ffcc00);
      border-radius: 20px;
      position: relative;
      overflow: hidden;
      margin-bottom: 80px;
    }

    .cta-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
    }

    .cta-content {
      max-width: 700px;
      margin: 0 auto;
      position: relative;
      z-index: 2;
    }

    .cta-content h2 {
      font-size: 2.8rem;
      color: var(--white);
      margin-bottom: 20px;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    .cta-content p {
      font-size: 1.2rem;
      color: rgba(255, 255, 255, 0.9);
      margin-bottom: 40px;
    }

    .btn {
      display: inline-block;
      padding: 18px 45px;
      background: var(--dark-color);
      color: var(--white);
      text-decoration: none;
      border-radius: 50px;
      font-weight: 700;
      transition: var(--transition);
      font-size: 1.1rem;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
      border: 2px solid rgba(255, 255, 255, 0.2);
      position: relative;
      overflow: hidden;
      z-index: 1;
    }

    .btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 0;
      height: 100%;
      background: rgba(255, 255, 255, 0.2);
      transition: var(--transition);
      z-index: -1;
    }

    .btn:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
    }

    .btn:hover::before {
      width: 100%;
    }

    .btn i {
      margin-right: 10px;
    }

    /* Footer */
    footer {
      background-color: var(--footer-bg);
      color: var(--white);
      padding: 70px 5% 30px;
    }

    .footer-content {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 40px;
      margin-bottom: 50px;
    }

    .footer-column h3 {
      font-size: 1.4rem;
      margin-bottom: 25px;
      position: relative;
      padding-bottom: 10px;
    }

    .footer-column h3::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 50px;
      height: 3px;
      background: var(--accent-color);
    }

    .footer-column p {
      margin-bottom: 20px;
      opacity: 0.8;
      line-height: 1.8;
    }

    .contact-info {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    .contact-item {
      display: flex;
      align-items: flex-start;
      gap: 15px;
    }

    .contact-item i {
      color: var(--accent-color);
      font-size: 1.2rem;
      margin-top: 3px;
    }

    .social-links {
      display: flex;
      gap: 15px;
      margin-top: 20px;
    }

    .social-links a {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      background: rgba(255, 255, 255, 0.1);
      color: var(--white);
      border-radius: 50%;
      transition: var(--transition);
    }

    .social-links a:hover {
      background: var(--accent-color);
      color: var(--dark-color);
      transform: translateY(-5px);
    }

    .footer-bottom {
      max-width: 1200px;
      margin: 0 auto;
      text-align: center;
      padding-top: 30px;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      font-size: 0.9rem;
      opacity: 0.7;
    }

    /* Responsive Design */
    @media (max-width: 992px) {
      .about-section {
        grid-template-columns: 1fr;
      }
      
      .about-image {
        max-width: 600px;
        margin: 0 auto;
      }
    }

    @media (max-width: 768px) {
      .mobile-menu-btn {
        display: block;
      }
      
      nav {
        position: fixed;
        top: 80px;
        right: -100%;
        flex-direction: column;
        background: var(--dark-color);
        width: 300px;
        height: calc(100vh - 80px);
        padding: 40px 20px;
        transition: var(--transition);
        box-shadow: -5px 0 15px rgba(0, 0, 0, 0.2);
      }
      
      nav.active {
        right: 0;
      }
      
      .page-title h1 {
        font-size: 2.5rem;
      }
    }

    @media (max-width: 576px) {
      .page-title h1 {
        font-size: 2.2rem;
      }
      
      .cta-content h2 {
        font-size: 2.2rem;
      }
    }
  </style>
</head>
<body>
  <header id="header">
    <div class="logo">
      <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'%3E%3Cpath fill='%23ffcc00' d='M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z'/%3E%3Cpath fill='%23ffffff' d='M12 18c3.31 0 6-2.69 6-6s-2.69-6-6-6-6 2.69-6 6 2.69 6 6 6zm-1-6.5v-3c0-.28.22-.5.5-.5s.5.22.5.5v3h1.5c.28 0 .5.22.5.5s-.22.5-.5.5h-4c-.28 0-.5-.22-.5-.5s.22-.5.5-.5H11z'/%3E%3C/svg%3E" alt="Logo">
      <h1>Auntie Eddah POS</h1>
    </div>
    <nav id="nav">
      <a href="index.php"><i class="fas fa-home"></i> Home</a>
      <a href="about.php" class="active"><i class="fas fa-info-circle"></i> About</a>
      <a href="about.php"><i class="fas fa-concierge-bell"></i> Services</a>
      <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
    </nav>
    <button class="mobile-menu-btn" id="menuBtn">
      <i class="fas fa-bars"></i>
    </button>
  </header>

  <div class="page-container">
    <!-- About Page -->
    <div id="about">
      <div class="page-title">
        <h1>About Auntie Eddah POS</h1>
        <p>Learn about our mission, values, and the team behind Africa's most trusted POS solution</p>
      </div>
      
      <div class="about-section">
        <div class="about-image">
          <img src="https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80" alt="About Auntie Eddah">
        </div>
        <div class="about-content">
          <h2>Our Story</h2>
          <p>Auntie Eddah POS began in 2010 as a small family business in Lilongwe, Malawi. Founded by Eddah Mwale, a seasoned entrepreneur with over 20 years of retail experience, our mission was simple: to help small businesses thrive through technology.</p>
          <p>What started as a basic point-of-sale system for our own shop has grown into Africa's leading retail management platform, serving thousands of businesses across 12 countries.</p>
          <p>Today, Auntie Eddah POS combines cutting-edge technology with deep understanding of African retail challenges to deliver solutions that drive real business growth.</p>
          
          <div class="values">
            <div class="value-card">
              <div class="value-icon">
                <i class="fas fa-hand-holding-heart"></i>
              </div>
              <h3>Community Focused</h3>
              <p>We build solutions that empower local businesses and communities.</p>
            </div>
            
            <div class="value-card">
              <div class="value-icon">
                <i class="fas fa-lightbulb"></i>
              </div>
              <h3>Innovative</h3>
              <p>Constantly evolving to meet the changing needs of African businesses.</p>
            </div>
            
            <div class="value-card">
              <div class="value-icon">
                <i class="fas fa-shield-alt"></i>
              </div>
              <h3>Reliable</h3>
              <p>Robust systems that work even in challenging environments.</p>
            </div>
            
            <div class="value-card">
              <div class="value-icon">
                <i class="fas fa-hands-helping"></i>
              </div>
              <h3>Supportive</h3>
              <p>Dedicated local support teams ready to help 24/7.</p>
            </div>
          </div>
        </div>
      </div>
      
      <div class="team-section">
        <div class="section-title">
          <h2>Meet Our Leadership Team</h2>
          <p>The passionate professionals driving our mission forward</p>
        </div>
        
        <div class="team-members">
          <div class="team-member">
            <div class="member-image">
              <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1376&q=80" alt="Eddah Mwale">
            </div>
            <div class="member-info">
              <h3>Eddah Mwale</h3>
              <div class="position">Founder & CEO</div>
              <p>20+ years retail experience. Passionate about empowering African entrepreneurs.</p>
              <div class="social-links">
                <a href="#"><i class="fab fa-linkedin-in"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-facebook-f"></i></a>
              </div>
            </div>
          </div>
          
          <div class="team-member">
            <div class="member-image">
              <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1374&q=80" alt="Thomas Banda">
            </div>
            <div class="member-info">
              <h3>Thomas Banda</h3>
              <div class="position">CTO</div>
              <p>Tech visionary with expertise in developing solutions for emerging markets.</p>
              <div class="social-links">
                <a href="#"><i class="fab fa-linkedin-in"></i></a>
                <a href="#"><i class="fab fa-github"></i></a>
              </div>
            </div>
          </div>
          
          <div class="team-member">
            <div class="member-image">
              <img src="https://images.unsplash.com/photo-1580489944761-15a19d654956?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1361&q=80" alt="Chikondi Phiri">
            </div>
            <div class="member-info">
              <h3>Chikondi Phiri</h3>
              <div class="position">Head of Customer Success</div>
              <p>Dedicated to ensuring every client maximizes the value of our solutions.</p>
              <div class="social-links">
                <a href="#"><i class="fab fa-linkedin-in"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Services Page -->
    <div id="services">
      <div class="page-title">
        <h1>Our Services</h1>
        <p>Comprehensive solutions designed to streamline your business operations</p>
      </div>
      
      <div class="services-section">
        <div class="section-title">
          <h2 style="color: var(--accent-color);">Core Offerings</h2>
          <p style="color: rgba(255,255,255,0.8);">Everything you need to run a successful retail business</p>
        </div>
        
        <div class="services-grid">
          <div class="service-card">
            <div class="service-icon">
              <i class="fas fa-cash-register"></i>
            </div>
            <h3>Point of Sale System</h3>
            <p>Intuitive, reliable POS with offline capabilities. Process sales, accept multiple payment methods, and print receipts.</p>
            <button class="btn">Learn More</button>
          </div>
          
          <div class="service-card">
            <div class="service-icon">
              <i class="fas fa-boxes"></i>
            </div>
            <h3>Inventory Management</h3>
            <p>Real-time tracking, low stock alerts, barcode scanning, and automated purchase ordering.</p>
            <button class="btn">Learn More</button>
          </div>
          
          <div class="service-card">
            <div class="service-icon">
              <i class="fas fa-chart-line"></i>
            </div>
            <h3>Business Analytics</h3>
            <p>Powerful reporting tools to track sales, profit margins, top products, and customer trends.</p>
            <button class="btn">Learn More</button>
          </div>
          
          <div class="service-card">
            <div class="service-icon">
              <i class="fas fa-users"></i>
            </div>
            <h3>Customer Management</h3>
            <p>Build customer profiles, track purchase history, and implement loyalty programs.</p>
            <button class="btn">Learn More</button>
          </div>
          
          <div class="service-card">
            <div class="service-icon">
              <i class="fas fa-mobile-alt"></i>
            </div>
            <h3>Mobile Integration</h3>
            <p>Manage your business from anywhere with our iOS and Android apps.</p>
            <button class="btn">Learn More</button>
          </div>
          
          <div class="service-card">
            <div class="service-icon">
              <i class="fas fa-headset"></i>
            </div>
            <h3>24/7 Support</h3>
            <p>Dedicated local support team available via phone, chat, and in-person visits.</p>
            <button class="btn">Learn More</button>
          </div>
        </div>
      </div>
      
      <div class="testimonials-section">
        <div class="section-title">
          <h2>Client Testimonials</h2>
          <p>Hear from businesses that transformed with Auntie Eddah POS</p>
        </div>
        
        <div class="testimonials">
          <div class="testimonial">
            <div class="testimonial-content">
              <p>"Since implementing Auntie Eddah POS, our inventory errors dropped by 80% and sales increased by 35% in just three months. The system paid for itself in the first month!"</p>
              <div class="client">
                <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1374&q=80" alt="Sarah K.">
                <div class="client-info">
                  <h4>Sarah Kanyenda</h4>
                  <div class="position">Owner, Sarah's Groceries</div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="testimonial">
            <div class="testimonial-content">
              <p>"The customer loyalty features have helped us retain 40% more customers. We now understand our customers better and can tailor promotions to their preferences."</p>
              <div class="client">
                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1374&q=80" alt="James T.">
                <div class="client-info">
                  <h4>James Tembo</h4>
                  <div class="position">Manager, QuickMart</div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="testimonial">
            <div class="testimonial-content">
              <p>"What impressed me most was the local support. When we had an issue, a technician was at our shop within 2 hours. That level of service is unmatched!"</p>
              <div class="client">
                <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1376&q=80" alt="Grace M.">
                <div class="client-info">
                  <h4>Grace Mwale</h4>
                  <div class="position">Owner, Grace's Boutique</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="cta-section">
      <div class="cta-content">
        <h2>Ready to Transform Your Business?</h2>
        <p>Join thousands of satisfied retailers using Auntie Eddah POS to streamline operations and boost profits</p>
        <a href="login.php" class="btn"><i class="fas fa-rocket"></i> Get Started Now</a>
      </div>
    </div>
  </div>

  <footer>
    <div class="footer-content">
      <div class="footer-column">
        <h3>About Us</h3>
        <p>Auntie Eddah POS is a modern point-of-sale system designed for African retailers. Our mission is to simplify your operations and help your business grow.</p>
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
          <li><a href="index.html">Home</a></li>
          <li><a href="#about">About Us</a></li>
          <li><a href="#services">Services</a></li>
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
    
    // Service card animations
    const serviceCards = document.querySelectorAll('.service-card');
    serviceCards.forEach(card => {
      card.addEventListener('mouseenter', () => {
        card.style.transform = 'translateY(-10px)';
      });
      
      card.addEventListener('mouseleave', () => {
        card.style.transform = 'translateY(0)';
      });
    });
    
    // Testimonial hover effect
    const testimonials = document.querySelectorAll('.testimonial');
    testimonials.forEach(testimonial => {
      testimonial.addEventListener('mouseenter', () => {
        testimonial.style.transform = 'translateY(-10px)';
      });
      
      testimonial.addEventListener('mouseleave', () => {
        testimonial.style.transform = 'translateY(0)';
      });
    });
    
    // Active navigation highlighting
    const currentPage = window.location.hash || '#about';
    document.querySelectorAll('nav a').forEach(link => {
      if (link.getAttribute('href') === currentPage) {
        link.classList.add('active');
      } else {
        link.classList.remove('active');
      }
    });
  </script>
</body>
</html>