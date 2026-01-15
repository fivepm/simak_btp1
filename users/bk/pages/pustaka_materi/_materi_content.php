<?php
// File ini dipanggil di dalam loop, jadi variabel $poin_data dan $admin_tingkat sudah tersedia
?>
<!-- Daftar File -->
<div class="space-y-3">
    <?php if (!empty($poin_data['files'])): foreach ($poin_data['files'] as $file): ?>
            <div class="border-b pb-3">
                <div class="flex justify-between items-center group">
                    <a href="../../uploads/materi/<?php echo htmlspecialchars($file['path_file'] ?? ''); ?>" target="_blank" class="font-semibold text-gray-700 hover:text-indigo-600">📄 <?php echo htmlspecialchars($file['nama_file_asli'] ?? 'File tidak valid'); ?></a>
                </div>
            </div>
    <?php endforeach;
    endif; ?>
</div>

<!-- Daftar Video -->
<div class="space-y-3 mt-2">
    <?php if (!empty($poin_data['videos'])): foreach ($poin_data['videos'] as $video): ?>
            <div class="pb-2">
                <div class="flex justify-between items-center group">
                    <p class="font-semibold text-gray-700">🎬 <?php echo htmlspecialchars($video['deskripsi_video'] ?: 'Video'); ?></p>
                </div>
                <div class="aspect-w-16 aspect-h-9 mt-1 bg-gray-200 rounded animate-pulse">
                    <!-- PERUBAHAN UTAMA: `src` dikosongkan, dan URL asli disimpan di `data-src` -->
                    <iframe
                        src=""
                        data-src="<?php echo get_gdrive_embed_url($video['url_video']); ?>"
                        frameborder="0"
                        allow="autoplay; encrypted-media"
                        allowfullscreen
                        class="w-full h-full rounded lazy-video">
                    </iframe>
                </div>
            </div>
    <?php endforeach;
    endif; ?>
</div>