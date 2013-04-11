<?php if(have_posts()) : ?>
	<section class="posts-section">
		<div class="container">
			<ul class="posts-list">
				<?php while(have_posts()) : the_post(); ?>
					<li id="post-<?php the_ID(); ?>" <?php post_class('three columns'); ?>>
						<article id="post-<?php the_ID(); ?>">
							<div class="post-content">
								<h3><a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>"><?php the_title(); ?></a></h3>
								<p class="meta">
									<span class="date"><?php the_date(); ?></span>
									<span class="author"><?php _e('by', 'mappress'); ?> <?php the_author(); ?></span>
								</p>
								<div class="post-excerpt">
									<?php the_excerpt(); ?>
								</div>
							</div>
							<aside class="actions">
								<a href="#"><?php _e('Locate on map', 'mappress'); ?></a>
								<a href="<?php the_permalink(); ?>"><?php _e('Read more', 'mappress'); ?></a>
							</aside>
						</article>
					</li>
				<?php endwhile; ?>
			</ul>
		</div>
	</section>
<?php endif; ?>