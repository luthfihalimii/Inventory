<?php if($errors->any()): ?>
<div class="alert error" role="alert" aria-live="assertive">
    <strong><i class="fa-solid fa-triangle-exclamation"></i> Ada <?php echo e($errors->count()); ?> hal yang perlu diperbaiki.</strong>
    <p style="margin:5px 0 0;font-size:12px">Periksa keterangan berikut, perbaiki data yang diminta, lalu kirim kembali formulir.</p>
    <ul class="error-list"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
</div>
<?php endif; ?>
<?php if(session('success')): ?><div class="alert" role="status"><i class="fa-solid fa-circle-check"></i> <?php echo e(session('success')); ?></div><?php endif; ?>
<?php if(session('error')): ?><div class="alert error" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <?php echo e(session('error')); ?> <button type="button" class="btn btn-ghost btn-sm" onclick="window.location.reload()" style="margin-left:8px">Muat ulang</button></div><?php endif; ?>
<?php /**PATH C:\wamp64\www\inventarisENT\resources\views/partials/errors.blade.php ENDPATH**/ ?>