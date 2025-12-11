        </div> <!-- End flex container -->
    </div> <!-- End min-h-screen -->
    
    <!-- Footer -->
    <footer class="theme-bg-card theme-border border-t mt-auto transition-all duration-300"
            :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
        <div class="py-4 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center text-sm theme-text-muted">
                <div class="mb-2 md:mb-0">
                    &copy; <?php echo date('Y'); ?> <?php echo e(config('app_name', 'Attendance System')); ?>. All rights reserved.
                </div>
                <div class="flex space-x-4">
                    <a href="<?php echo config('app_url'); ?>/pages/help.php" class="hover:text-violet-600 transition-colors">Help</a>
                    <a href="<?php echo config('app_url'); ?>/pages/privacy.php" class="hover:text-violet-600 transition-colors">Privacy</a>
                    <a href="<?php echo config('app_url'); ?>/pages/terms.php" class="hover:text-violet-600 transition-colors">Terms</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
