<?php
/**
 * Страница истории обновлений
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>📜 История обновлений фида</h1>
    
    <div class="yfgp-history-section">
        <div class="yfgp-card">
            <h2>📊 Последние обновления</h2>
            
            <?php if (!empty($history)): ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Дата и время</th>
                            <th>Статус</th>
                            <th>Сообщение</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($history) as $entry): ?>
                            <tr>
                                <td><?php echo esc_html($entry['date']); ?></td>
                                <td>
                                    <?php if ($entry['status'] === 'success'): ?>
                                        <span class="yfgp-status-success">✅ Успешно</span>
                                    <?php else: ?>
                                        <span class="yfgp-status-error">❌ Ошибка</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($entry['message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>История пуста. Создайте первый фид!</p>
            <?php endif; ?>
        </div>
        
        <div class="yfgp-card">
            <h2>💾 Бэкапы фидов</h2>
            
            <?php if (!empty($backups)): ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Файл</th>
                            <th>Дата создания</th>
                            <th>Размер</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td><code><?php echo esc_html($backup['file']); ?></code></td>
                                <td><?php echo esc_html($backup['date']); ?></td>
                                <td><?php echo size_format($backup['size']); ?></td>
                                <td>
                                    <button type="button" 
                                            class="button button-small yfgp-restore-backup" 
                                            data-file="<?php echo esc_attr($backup['file']); ?>">
                                        ↩️ Восстановить
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Нет доступных бэкапов.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.yfgp-history-section {
    margin-top: 20px;
}

.yfgp-card {
    background: #fff;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.yfgp-status-success {
    color: #00a32a;
    font-weight: 600;
}

.yfgp-status-error {
    color: #d63638;
    font-weight: 600;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.yfgp-restore-backup').on('click', function() {
        if (!confirm('Восстановить этот бэкап? Текущий фид будет перезаписан.')) {
            return;
        }
        
        const button = $(this);
        const file = button.data('file');
        
        button.prop('disabled', true).text('⏳ Восстановление...');
        
        $.ajax({
            url: yfgpAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'yfgp_restore_backup',
                nonce: yfgpAjax.nonce,
                file: file
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Бэкап восстановлен!');
                    location.reload();
                } else {
                    alert('❌ Ошибка: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Ошибка сети');
            },
            complete: function() {
                button.prop('disabled', false).text('↩️ Восстановить');
            }
        });
    });
});
</script>

