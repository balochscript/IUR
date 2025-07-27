<?php
// دریافت پست‌هایی که متادیتای آپلود دارند
$posts = get_posts([
  'post_type'    => ['post', 'page', 'product'],
  'numberposts'  => -1,
  'meta_query'   => [[
    'key'     => '_iur_upload_status',
    'compare' => 'EXISTS'
  ]]
]);

?>

<h2 style="color: #6A9CA5;">📊 Upload Status Report for Post Images</h2>

<!-- نوار پیشرفت -->
<div id="iur-progress-container" style="margin: 20px 0; background: #f0f9fb; border: 1px solid #6A9CA5; border-radius: 6px; position: relative; height: 26px;">
  <div id="iur-progress-bar" style="width: 0%; height: 100%; background: #4CAF50; border-radius: 6px; transition: width 0.4s ease;"></div>
  <span id="iur-progress-label" style="position: absolute; top: 0; left: 50%; transform: translateX(-50%); font-weight: bold; color: #444;">0%</span>
</div>

<!-- جدول گزارش -->
<table class="widefat striped" style="margin-top: 20px;">
  <thead>
    <tr>
      <th>Post</th>
      <th>Upload Service</th>
      <th>Total Images</th>
      <th>Successful</th>
      <th>Success Rate</th>
      <th>Status</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($posts as $post):
      $report = get_post_meta($post->ID, '_iur_upload_status', true);
      if (empty($report)) continue;

      $images = is_array($report['images'] ?? null) ? $report['images'] : [];
      $total_images = count($images);
      $success_images = 0;

      foreach ($images as $img) {
        if (!empty($img['success']) && filter_var($img['uploaded_url'] ?? '', FILTER_VALIDATE_URL)) {
          $success_images++;
        }
      }

      $percent = $total_images > 0 ? round(($success_images / $total_images) * 100) : 0;
      $status  = esc_html($report['status'] ?? 'unknown');
    ?>
    <tr>
      <td><?php echo esc_html($post->post_title); ?></td>
      <td><?php echo esc_html($report['service'] ?? '-'); ?></td>
      <td><?php echo $total_images; ?></td>
      <td><?php echo $success_images; ?></td>
      <td><?php echo ($total_images > 0 ? $percent . '%' : 'N/A'); ?></td>
      <td><?php echo $status; ?></td>
      <td>
        <button class="iur-process-post button"
                data-postid="<?php echo esc_attr($post->ID); ?>"
                data-nonce="<?php echo wp_create_nonce('iur_process_post'); ?>">
          📤 Process
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- JavaScript برای دکمه Process -->
<script>
document.querySelectorAll('.iur-process-post').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    const postId = this.dataset.postid;
    const nonce  = this.dataset.nonce;

    this.disabled = true;
    this.textContent = '⏳ Processing...';

    fetch(ajaxurl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action:  'iur_process_post',
        post_id: postId,
        nonce:   nonce
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        this.textContent = '✅ Done';
        const row = this.closest('tr');
        row.children[4].textContent = data.data.percent + '%';
        row.children[5].textContent = data.data.status;
      } else {
        this.textContent = '❌ Failed';
        console.error(data.data);
      }
    })
    .catch(err => {
      this.textContent = '❌ Error';
      console.error(err);
    });
  });
});
</script>
