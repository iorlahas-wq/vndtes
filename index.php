<?php

require_once 'includes/init.php';

$pageTitle = "Welcome";

require_once 'includes/layout_start.php';

?>

<!-- HERO SECTION -->

<section class="hero-section">

<div class="container">

<div class="row align-items-center">

<div class="col-lg-6">

<span class="badge bg-primary mb-3">

Virtual Network Training Platform

</span>

<h1 class="display-4 fw-bold mb-4">

Train.<br>

Diagnose.<br>

Master Networking.

</h1>

<p class="lead text-muted">

VNDTES is an intelligent virtual laboratory that enables
Networking & Cloud Computing students to practice
real-world troubleshooting using simulated enterprise
network environments with automated diagnostic support.

</p>

<div class="mt-4">

<a href="<?= APP_URL ?>/auth/login.php"
class="btn btn-primary btn-lg px-5 me-3">

<i class="bi bi-box-arrow-in-right"></i>

Login

</a>

<a href="#features"
class="btn btn-outline-primary btn-lg px-4">

Learn More

</a>

</div>

<hr class="my-5">

<div class="row">

<div class="col-md-6">

<div class="card shadow-sm">

<div class="card-body">

<h5>

<i class="bi bi-cpu"></i>

System Status

</h5>

<ul class="list-group list-group-flush">

<li class="list-group-item">

🟢 Database Connected

</li>

<li class="list-group-item">

🟢 Simulation Ready

</li>

<li class="list-group-item">

🟢 Diagnostic Engine Ready

</li>

</ul>

</div>

</div>

</div>

<div class="col-md-6">

<div class="card shadow-sm">

<div class="card-body">

<h5>

<i class="bi bi-activity"></i>

Live Network Status

</h5>

<p id="networkMessage"

class="lead text-success">

Loading...

</p>

</div>

</div>

</div>

</div>

</div>

<div class="col-lg-6 text-center">

<div class="network-panel">

<img
src="<?= APP_URL ?>/assets/images/network-topology.svg"
class="img-fluid hero-svg"
alt="VNDTES Network">

<p class="network-status">

<span class="status-dot"></span>

Virtual Lab Online

</p>

</div>

</div>

</div>

</div>

</section>

<!-- STATISTICS -->

<section class="container py-5">

<div class="row text-center">

<div class="col-md-3 col-6 mb-4">

<h2 class="text-primary fw-bold">50+</h2>

<p>Network Scenarios</p>

</div>

<div class="col-md-3 col-6 mb-4">

<h2 class="text-success fw-bold">100%</h2>

<p>Hands-on Practice</p>

</div>

<div class="col-md-3 col-6 mb-4">

<h2 class="text-warning fw-bold">24/7</h2>

<p>Learning Access</p>

</div>

<div class="col-md-3 col-6 mb-4">

<h2 class="text-danger fw-bold">AI</h2>

<p>Diagnostic Support</p>

</div>

</div>

</section>

<!-- FEATURES -->

<section id="features" class="container py-5">

<div class="text-center mb-5">

<h2 class="fw-bold">

Powerful Learning Features

</h2>

<p class="text-muted">

Everything students need to become confident network troubleshooters.

</p>

</div>

<div class="row g-4">

<div class="col-md-4">

<div class="card feature-card h-100">

<div class="card-body text-center">

<div class="feature-icon bg-primary">

<i class="bi bi-router-fill"></i>

</div>

<h4>

Virtual Network Lab

</h4>

<p>

Practice configuring routers,
switches, servers and PCs
inside realistic network topologies.

</p>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card feature-card h-100">

<div class="card-body text-center">

<div class="feature-icon bg-success">

<i class="bi bi-cpu-fill"></i>

</div>

<h4>

Intelligent Diagnosis

</h4>

<p>

Receive smart hints and
guided troubleshooting while
solving networking faults.

</p>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card feature-card h-100">

<div class="card-body text-center">

<div class="feature-icon bg-warning">

<i class="bi bi-graph-up-arrow"></i>

</div>

<h4>

Performance Analytics

</h4>

<p>

Track your progress,
identify weak areas,
and improve continuously.

</p>

</div>

</div>

</div>

</div>

</section>

<!-- CALL TO ACTION -->

<section class="container py-5">

<div class="card bg-primary text-white border-0 shadow">

<div class="card-body text-center p-5">

<h2>

Ready to begin your networking journey?

</h2>

<p class="lead">

Sign in and start solving real-world
network troubleshooting scenarios.

</p>

<a href="<?= APP_URL ?>/auth/login.php"

class="btn btn-light btn-lg px-5">

Get Started

</a>

</div>

</div>

</section>

<?php

require_once 'includes/layout_end.php';

?>