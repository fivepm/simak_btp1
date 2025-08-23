<?php
// File ini dipanggil di dalam loop, jadi variabel $poin_data sudah tersedia
?>
<!-- Daftar File -->
<div class="space-y-3">
    <?php if (!empty($poin_data['files'])): foreach ($poin_data['files'] as $file): ?>
            <div class="border-b pb-3">
                <div class="flex justify-between items-center">
                    <a href="../uploads/materi/<?php echo htmlspecialchars($file['path_file'] ?? ''); ?>" target="_blank" class="font-semibold text-gray-700 hover:text-indigo-600">📄 <?php echo htmlspecialchars($file['nama_file_asli'] ?? 'File tidak valid'); ?></a>
                    <form method="POST" action="<?php echo $redirect_url; ?>" onsubmit="return confirm('Yakin ingin menghapus file ini?');">
                        <input type="hidden" name="action" value="hapus_file">
                        <input type="hidden" name="id" value="<?php echo $file['id']; ?>">
                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs">[Hapus]</button>
                    </form>
                </div>
            </div>
    <?php endforeach;
    endif; ?>
</div>

<!-- Daftar Video -->
<div class="space-y-3 mt-2">
    <?php if (!empty($poin_data['videos'])): foreach ($poin_data['videos'] as $video): ?>
            <div class="pb-2">
                <div class="flex justify-between items-center">
                    <p class="font-semibold text-gray-700">🎬 <?php echo htmlspecialchars($video['deskripsi_video'] ?: 'Video'); ?></p>
                    <form method="POST" action="<?php echo $redirect_url; ?>" onsubmit="return confirm('Yakin ingin menghapus video ini?');">
                        <input type="hidden" name="action" value="hapus_video">
                        <input type="hidden" name="id" value="<?php echo $video['id']; ?>">
                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs">[Hapus]</button>
                    </form>
                </div>
                <div class="aspect-w-16 aspect-h-9 mt-1">
                    <iframe src="<?php echo get_gdrive_embed_url($video['url_video']); ?>" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen class="w-full h-full rounded"></iframe>
                </div>
            </div>
    <?php endforeach;
    endif; ?>
</div>

<!-- Tombol Aksi -->
<div class="mt-3 space-x-4">
    <button class="upload-file-btn text-sm text-green-600 hover:underline" data-poin-id="<?php echo $poin_data['id']; ?>">+ Upload File</button>
    <button class="add-video-btn text-sm text-blue-600 hover:underline" data-poin-id="<?php echo $poin_data['id']; ?>">+ Tambah Video</button>
</div>