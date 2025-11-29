<?php
require_once 'includes/header.php';
require_once 'includes/functions.php';

$services = getServices($conn);
?>

<section class="hero">
    <div class="container">
        <h1>Find Your Inner Peace</h1>
        <p>Escape the noise and rediscover tranquility at Kallma Spa & Wellness Centre. Expert masseuses, premium treatments, and a sanctuary for your soul.</p>
        <a href="#services" class="btn btn-primary">Explore Services</a>
    </div>
</section>

<section id="services" class="container">
    <h2 class="section-title">Our Services</h2>
    <div class="services-grid">
        <?php foreach ($services as $service): ?>
            <div class="glass-card service-card">
                <?php if ($service['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($service['image_url']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>" class="service-image">
                <?php else: ?>
                    <div class="service-image" style="background: #334155; display: flex; align-items: center; justify-content: center;">
                        <span>No Image</span>
                    </div>
                <?php endif; ?>
                <div class="service-info">
                    <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                    <p style="color: #94a3b8; margin-bottom: 1rem;"><?php echo htmlspecialchars($service['description']); ?></p>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="service-price">$<?php echo number_format($service['price'], 2); ?></span>
                        <span style="color: #64748b; font-size: 0.9rem;"><?php echo $service['duration_minutes']; ?> mins</span>
                    </div>
                    <a href="booking.php?service_id=<?php echo $service['id']; ?>" class="btn btn-outline" style="margin-top: 1rem; width: 100%; text-align: center;">Book Now</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
