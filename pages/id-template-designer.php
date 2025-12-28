<?php
/**
 * ID Template Designer Page
 * Visual drag-and-drop editor for customizing student ID card templates
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

// Require admin role for template design
requireRole('admin');

$pageTitle = 'ID Template Designer';

// Handle template save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validateCsrfToken($_POST['csrf_token'] ?? '');
    
    if ($_POST['action'] === 'save_template') {
        $templateData = $_POST['template_data'] ?? '';
        $templateName = sanitizeString($_POST['template_name'] ?? 'default');
        $templateSide = sanitizeString($_POST['template_side'] ?? 'front');
        
        // Save to database or file
        $templatePath = __DIR__ . '/../storage/templates/';
        if (!is_dir($templatePath)) {
            mkdir($templatePath, 0755, true);
        }
        
        $filename = $templatePath . 'id_template_' . $templateSide . '.json';
        file_put_contents($filename, $templateData);
        
        setFlash('success', 'Template saved successfully!');
        header('Location: ' . config('app_url') . '/pages/id-template-designer.php');
        exit;
    }
}

// Load existing templates
$frontTemplate = null;
$backTemplate = null;
$templatePath = __DIR__ . '/../storage/templates/';

if (file_exists($templatePath . 'id_template_front.json')) {
    $frontTemplate = file_get_contents($templatePath . 'id_template_front.json');
}
if (file_exists($templatePath . 'id_template_back.json')) {
    $backTemplate = file_get_contents($templatePath . 'id_template_back.json');
}

// Get sample student for preview
$sampleStudent = dbFetchOne("SELECT * FROM students WHERE is_active = 1 LIMIT 1");
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="mt-16 bg-gray-50/50 min-h-screen transition-all duration-300 w-full"
      :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-semibold text-gray-900 tracking-tight">ID Template Designer</h1>
                    <p class="text-gray-500 mt-1">Customize your student ID card layout with drag and drop</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="previewTemplate()" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Preview
                    </button>
                    <button onclick="saveTemplate()" class="inline-flex items-center px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-xl hover:bg-violet-700 transition-colors shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        Save Template
                    </button>
                </div>
            </div>
        </div>
        
        <?php echo displayFlash(); ?>
        
        <div class="grid grid-cols-1 xl:grid-cols-4 gap-6" x-data="idTemplateDesigner()">
            
            <!-- Left Panel: Elements Toolbox -->
            <div class="xl:col-span-1">
                <div class="bg-white rounded-xl border border-gray-100 p-4 sticky top-20">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Elements</h3>
                    
                    <!-- Draggable Elements -->
                    <div class="space-y-2">
                        <div draggable="true" @dragstart="dragStart($event, 'text')" 
                             class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-move hover:bg-gray-100 transition-colors border border-transparent hover:border-violet-200">
                            <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Text Label</span>
                        </div>
                        
                        <div draggable="true" @dragstart="dragStart($event, 'field')"
                             class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-move hover:bg-gray-100 transition-colors border border-transparent hover:border-violet-200">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Data Field</span>
                        </div>
                        
                        <div draggable="true" @dragstart="dragStart($event, 'image')"
                             class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-move hover:bg-gray-100 transition-colors border border-transparent hover:border-violet-200">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Image/Logo</span>
                        </div>
                        
                        <div draggable="true" @dragstart="dragStart($event, 'photo')"
                             class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-move hover:bg-gray-100 transition-colors border border-transparent hover:border-violet-200">
                            <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Student Photo</span>
                        </div>
                        
                        <div draggable="true" @dragstart="dragStart($event, 'qrcode')"
                             class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-move hover:bg-gray-100 transition-colors border border-transparent hover:border-violet-200">
                            <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-purple-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M3 3h6v6H3V3zm2 2v2h2V5H5zm8-2h6v6h-6V3zm2 2v2h2V5h-2zM3 13h6v6H3v-6zm2 2v2h2v-2H5zm13-2h3v2h-3v-2zm-3 0h2v3h-2v-3zm3 3h3v3h-3v-3zm-3 3h2v2h-2v-2z"/>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700">QR Code</span>
                        </div>
                        
                        <div draggable="true" @dragstart="dragStart($event, 'barcode')"
                             class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-move hover:bg-gray-100 transition-colors border border-transparent hover:border-violet-200">
                            <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2 4h2v16H2V4zm4 0h1v16H6V4zm3 0h2v16H9V4zm4 0h1v16h-1V4zm3 0h2v16h-2V4zm4 0h2v16h-2V4z"/>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700">QR Code</span>
                        </div>
                        
                        <div draggable="true" @dragstart="dragStart($event, 'shape')"
                             class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-move hover:bg-gray-100 transition-colors border border-transparent hover:border-violet-200">
                            <div class="w-8 h-8 bg-pink-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Shape/Box</span>
                        </div>
                        
                        <div draggable="true" @dragstart="dragStart($event, 'line')"
                             class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-move hover:bg-gray-100 transition-colors border border-transparent hover:border-violet-200">
                            <div class="w-8 h-8 bg-cyan-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"/>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Line/Divider</span>
                        </div>
                    </div>
                    
                    <!-- Data Fields Reference -->
                    <div class="mt-6 pt-4 border-t border-gray-100">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase mb-3">Available Data Fields</h4>
                        <div class="space-y-1 text-xs text-gray-600">
                            <p class="font-mono bg-gray-50 px-2 py-1 rounded">{student_name}</p>
                            <p class="font-mono bg-gray-50 px-2 py-1 rounded">{first_name}</p>
                            <p class="font-mono bg-gray-50 px-2 py-1 rounded">{last_name}</p>
                            <p class="font-mono bg-gray-50 px-2 py-1 rounded">{lrn}</p>
                            <p class="font-mono bg-gray-50 px-2 py-1 rounded">{student_id}</p>
                            <p class="font-mono bg-gray-50 px-2 py-1 rounded">{grade_section}</p>
                            <p class="font-mono bg-gray-50 px-2 py-1 rounded">{school_year}</p>
                            <p class="font-mono bg-gray-50 px-2 py-1 rounded">{school_name}</p>
                            <p class="font-mono bg-gray-50 px-2 py-1 rounded">{parent_name}</p>
                            <p class="font-mono bg-gray-50 px-2 py-1 rounded">{parent_phone}</p>
                            <p class="font-mono bg-gray-50 px-2 py-1 rounded">{address}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Center: Canvas Area -->
            <div class="xl:col-span-2">
                <div class="bg-white rounded-xl border border-gray-100 p-4">
                    <!-- Card Side Tabs -->
                    <div class="flex gap-2 mb-4">
                        <button @click="activeSide = 'front'" 
                                :class="activeSide === 'front' ? 'bg-violet-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors">
                            Front Side
                        </button>
                        <button @click="activeSide = 'back'"
                                :class="activeSide === 'back' ? 'bg-violet-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors">
                            Back Side
                        </button>
                    </div>
                    
                    <!-- Canvas Container -->
                    <div class="flex justify-center">
                        <div class="relative">
                            <!-- ID Card Canvas - Front -->
                            <div x-show="activeSide === 'front'"
                                 id="canvas-front"
                                 @drop="dropElement($event, 'front')"
                                 @dragover.prevent
                                 @click="deselectAll($event)"
                                 class="relative bg-gradient-to-br from-gray-800 via-gray-700 to-red-800 rounded-xl overflow-hidden shadow-xl"
                                 :style="'width: ' + cardWidth + 'px; height: ' + cardHeight + 'px;'">
                                
                                <!-- Grid overlay for alignment -->
                                <div class="absolute inset-0 pointer-events-none opacity-20"
                                     style="background-image: linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 20px 20px;">
                                </div>
                                
                                <!-- Draggable Elements -->
                                <template x-for="(element, index) in frontElements" :key="element.id">
                                    <div :id="'element-' + element.id"
                                         :class="{'ring-2 ring-violet-500 ring-offset-2': selectedElement?.id === element.id}"
                                         class="absolute cursor-move select-none"
                                         :style="getElementStyle(element)"
                                         @mousedown="startDrag($event, element)"
                                         @click.stop="selectElement(element)">
                                        
                                        <!-- Text Element -->
                                        <template x-if="element.type === 'text'">
                                            <span :style="getTextStyle(element)" x-text="element.content"></span>
                                        </template>
                                        
                                        <!-- Data Field Element -->
                                        <template x-if="element.type === 'field'">
                                            <span :style="getTextStyle(element)" x-text="getFieldPreview(element.field)"></span>
                                        </template>
                                        
                                        <!-- Image Element -->
                                        <template x-if="element.type === 'image'">
                                            <div :style="'width: ' + element.width + 'px; height: ' + element.height + 'px;'"
                                                 class="bg-white/20 border-2 border-dashed border-white/40 rounded flex items-center justify-center">
                                                <template x-if="element.src">
                                                    <img :src="element.src" class="w-full h-full object-contain rounded">
                                                </template>
                                                <template x-if="!element.src">
                                                    <span class="text-white/60 text-xs">Logo</span>
                                                </template>
                                            </div>
                                        </template>
                                        
                                        <!-- Student Photo Element -->
                                        <template x-if="element.type === 'photo'">
                                            <div :style="'width: ' + element.width + 'px; height: ' + element.height + 'px;'"
                                                 class="bg-white/10 border-2 border-red-500 rounded flex items-center justify-center">
                                                <svg class="w-8 h-8 text-red-400" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                                </svg>
                                            </div>
                                        </template>
                                        
                                        <!-- QR Code Element -->
                                        <template x-if="element.type === 'qrcode'">
                                            <div :style="'width: ' + element.width + 'px; height: ' + element.height + 'px;'"
                                                 class="bg-white rounded p-2 flex items-center justify-center">
                                                <svg class="w-full h-full" viewBox="0 0 100 100">
                                                    <rect x="10" y="10" width="30" height="30" fill="black"/>
                                                    <rect x="15" y="15" width="20" height="20" fill="white"/>
                                                    <rect x="20" y="20" width="10" height="10" fill="black"/>
                                                    <rect x="60" y="10" width="30" height="30" fill="black"/>
                                                    <rect x="65" y="15" width="20" height="20" fill="white"/>
                                                    <rect x="70" y="20" width="10" height="10" fill="black"/>
                                                    <rect x="10" y="60" width="30" height="30" fill="black"/>
                                                    <rect x="15" y="65" width="20" height="20" fill="white"/>
                                                    <rect x="20" y="70" width="10" height="10" fill="black"/>
                                                    <rect x="50" y="50" width="10" height="10" fill="black"/>
                                                    <rect x="70" y="50" width="10" height="10" fill="black"/>
                                                    <rect x="50" y="70" width="10" height="10" fill="black"/>
                                                    <rect x="60" y="60" width="10" height="10" fill="black"/>
                                                    <rect x="80" y="60" width="10" height="10" fill="black"/>
                                                    <rect x="60" y="80" width="10" height="10" fill="black"/>
                                                    <rect x="80" y="80" width="10" height="10" fill="black"/>
                                                </svg>
                                            </div>
                                        </template>
                                        
                                        <!-- Barcode Element -->
                                        <template x-if="element.type === 'barcode'">
                                            <div :style="'width: ' + element.width + 'px; height: ' + element.height + 'px;'"
                                                 class="bg-white rounded p-1 flex items-center justify-center">
                                                <svg class="w-full h-full" viewBox="0 0 200 60">
                                                    <rect x="10" y="5" width="3" height="40" fill="black"/>
                                                    <rect x="15" y="5" width="1" height="40" fill="black"/>
                                                    <rect x="20" y="5" width="2" height="40" fill="black"/>
                                                    <rect x="25" y="5" width="4" height="40" fill="black"/>
                                                    <rect x="32" y="5" width="1" height="40" fill="black"/>
                                                    <rect x="36" y="5" width="3" height="40" fill="black"/>
                                                    <rect x="42" y="5" width="2" height="40" fill="black"/>
                                                    <rect x="48" y="5" width="1" height="40" fill="black"/>
                                                    <rect x="52" y="5" width="4" height="40" fill="black"/>
                                                    <rect x="60" y="5" width="2" height="40" fill="black"/>
                                                    <rect x="66" y="5" width="1" height="40" fill="black"/>
                                                    <rect x="70" y="5" width="3" height="40" fill="black"/>
                                                    <text x="100" y="55" text-anchor="middle" font-size="8" fill="black">123456789012</text>
                                                </svg>
                                            </div>
                                        </template>
                                        
                                        <!-- Shape Element -->
                                        <template x-if="element.type === 'shape'">
                                            <div :style="'width: ' + element.width + 'px; height: ' + element.height + 'px; background-color: ' + element.bgColor + '; border-radius: ' + element.borderRadius + 'px; border: ' + element.borderWidth + 'px solid ' + element.borderColor + ';'">
                                            </div>
                                        </template>
                                        
                                        <!-- Line Element -->
                                        <template x-if="element.type === 'line'">
                                            <div :style="'width: ' + element.width + 'px; height: ' + element.height + 'px; background-color: ' + element.color + ';'">
                                            </div>
                                        </template>
                                        
                                        <!-- Resize Handle -->
                                        <div x-show="selectedElement?.id === element.id && element.type !== 'text' && element.type !== 'field'"
                                             @mousedown.stop="startResize($event, element)"
                                             class="absolute -bottom-1 -right-1 w-3 h-3 bg-violet-500 rounded-full cursor-se-resize">
                                        </div>
                                    </div>
                                </template>
                            </div>
                            
                            <!-- ID Card Canvas - Back -->
                            <div x-show="activeSide === 'back'"
                                 id="canvas-back"
                                 @drop="dropElement($event, 'back')"
                                 @dragover.prevent
                                 @click="deselectAll($event)"
                                 class="relative bg-gradient-to-br from-red-800 via-gray-800 to-blue-900 rounded-xl overflow-hidden shadow-xl"
                                 :style="'width: ' + cardWidth + 'px; height: ' + cardHeight + 'px;'">
                                
                                <!-- Grid overlay -->
                                <div class="absolute inset-0 pointer-events-none opacity-20"
                                     style="background-image: linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 20px 20px;">
                                </div>
                                
                                <!-- Back Elements -->
                                <template x-for="(element, index) in backElements" :key="element.id">
                                    <div :id="'element-' + element.id"
                                         :class="{'ring-2 ring-violet-500 ring-offset-2': selectedElement?.id === element.id}"
                                         class="absolute cursor-move select-none"
                                         :style="getElementStyle(element)"
                                         @mousedown="startDrag($event, element)"
                                         @click.stop="selectElement(element)">
                                        
                                        <!-- Same element templates as front -->
                                        <template x-if="element.type === 'text'">
                                            <span :style="getTextStyle(element)" x-text="element.content"></span>
                                        </template>
                                        <template x-if="element.type === 'field'">
                                            <span :style="getTextStyle(element)" x-text="getFieldPreview(element.field)"></span>
                                        </template>
                                        <template x-if="element.type === 'image'">
                                            <div :style="'width: ' + element.width + 'px; height: ' + element.height + 'px;'"
                                                 class="bg-white/20 border-2 border-dashed border-white/40 rounded flex items-center justify-center">
                                                <template x-if="element.src">
                                                    <img :src="element.src" class="w-full h-full object-contain rounded">
                                                </template>
                                                <template x-if="!element.src">
                                                    <span class="text-white/60 text-xs">Logo</span>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="element.type === 'photo'">
                                            <div :style="'width: ' + element.width + 'px; height: ' + element.height + 'px;'"
                                                 class="bg-white/10 border-2 border-red-500 rounded flex items-center justify-center">
                                                <svg class="w-8 h-8 text-red-400" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                                </svg>
                                            </div>
                                        </template>
                                        <template x-if="element.type === 'qrcode'">
                                            <div :style="'width: ' + element.width + 'px; height: ' + element.height + 'px;'"
                                                 class="bg-white rounded p-2 flex items-center justify-center">
                                                <svg class="w-full h-full" viewBox="0 0 100 100">
                                                    <rect x="10" y="10" width="30" height="30" fill="black"/>
                                                    <rect x="15" y="15" width="20" height="20" fill="white"/>
                                                    <rect x="20" y="20" width="10" height="10" fill="black"/>
                                                    <rect x="60" y="10" width="30" height="30" fill="black"/>
                                                    <rect x="65" y="15" width="20" height="20" fill="white"/>
                                                    <rect x="70" y="20" width="10" height="10" fill="black"/>
                                                    <rect x="10" y="60" width="30" height="30" fill="black"/>
                                                    <rect x="15" y="65" width="20" height="20" fill="white"/>
                                                    <rect x="20" y="70" width="10" height="10" fill="black"/>
                                                </svg>
                                            </div>
                                        </template>
                                        <template x-if="element.type === 'barcode'">
                                            <div :style="'width: ' + element.width + 'px; height: ' + element.height + 'px;'"
                                                 class="bg-white rounded p-1 flex items-center justify-center">
                                                <svg class="w-full h-full" viewBox="0 0 200 60">
                                                    <rect x="10" y="5" width="3" height="40" fill="black"/>
                                                    <rect x="15" y="5" width="1" height="40" fill="black"/>
                                                    <rect x="20" y="5" width="2" height="40" fill="black"/>
                                                    <text x="100" y="55" text-anchor="middle" font-size="8" fill="black">123456789012</text>
                                                </svg>
                                            </div>
                                        </template>
                                        <template x-if="element.type === 'shape'">
                                            <div :style="'width: ' + element.width + 'px; height: ' + element.height + 'px; background-color: ' + element.bgColor + '; border-radius: ' + element.borderRadius + 'px; border: ' + element.borderWidth + 'px solid ' + element.borderColor + ';'">
                                            </div>
                                        </template>
                                        <template x-if="element.type === 'line'">
                                            <div :style="'width: ' + element.width + 'px; height: ' + element.height + 'px; background-color: ' + element.color + ';'">
                                            </div>
                                        </template>
                                        
                                        <div x-show="selectedElement?.id === element.id && element.type !== 'text' && element.type !== 'field'"
                                             @mousedown.stop="startResize($event, element)"
                                             class="absolute -bottom-1 -right-1 w-3 h-3 bg-violet-500 rounded-full cursor-se-resize">
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card Size Controls -->
                    <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-center gap-4">
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-gray-500">Width:</label>
                            <input type="number" x-model.number="cardWidth" min="150" max="400" 
                                   class="w-16 px-2 py-1 text-xs border border-gray-200 rounded">
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-gray-500">Height:</label>
                            <input type="number" x-model.number="cardHeight" min="200" max="500"
                                   class="w-16 px-2 py-1 text-xs border border-gray-200 rounded">
                        </div>
                        <button @click="resetToDefault()" class="text-xs text-violet-600 hover:text-violet-700">
                            Reset to Default
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Properties Editor -->
            <div class="xl:col-span-1">
                <div class="bg-white rounded-xl border border-gray-100 p-4 sticky top-20">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Properties</h3>
                    
                    <!-- No Selection State -->
                    <div x-show="!selectedElement" class="text-center py-8 text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
                        </svg>
                        <p class="text-sm">Select an element to edit its properties</p>
                    </div>
                    
                    <!-- Element Properties -->
                    <div x-show="selectedElement" class="space-y-4">
                        <!-- Position -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-2">Position</label>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-xs text-gray-400">X</label>
                                    <input type="number" x-model.number="selectedElement.x" @input="updateElement()"
                                           class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-lg">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400">Y</label>
                                    <input type="number" x-model.number="selectedElement.y" @input="updateElement()"
                                           class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-lg">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Size (for resizable elements) -->
                        <div x-show="selectedElement?.type !== 'text' && selectedElement?.type !== 'field'">
                            <label class="block text-xs font-medium text-gray-500 mb-2">Size</label>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-xs text-gray-400">Width</label>
                                    <input type="number" x-model.number="selectedElement.width" @input="updateElement()"
                                           class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-lg">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400">Height</label>
                                    <input type="number" x-model.number="selectedElement.height" @input="updateElement()"
                                           class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-lg">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Text Content (for text elements) -->
                        <div x-show="selectedElement?.type === 'text'">
                            <label class="block text-xs font-medium text-gray-500 mb-2">Text Content</label>
                            <input type="text" x-model="selectedElement.content" @input="updateElement()"
                                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg">
                        </div>
                        
                        <!-- Data Field Selection -->
                        <div x-show="selectedElement?.type === 'field'">
                            <label class="block text-xs font-medium text-gray-500 mb-2">Data Field</label>
                            <select x-model="selectedElement.field" @change="updateElement()"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg">
                                <option value="student_name">Student Full Name</option>
                                <option value="first_name">First Name</option>
                                <option value="last_name">Last Name</option>
                                <option value="lrn">LRN</option>
                                <option value="student_id">Student ID</option>
                                <option value="grade_section">Grade & Section</option>
                                <option value="school_year">School Year</option>
                                <option value="school_name">School Name</option>
                                <option value="parent_name">Parent Name</option>
                                <option value="parent_phone">Parent Phone</option>
                                <option value="address">Address</option>
                            </select>
                        </div>
                        
                        <!-- Font Properties (for text/field) -->
                        <div x-show="selectedElement?.type === 'text' || selectedElement?.type === 'field'">
                            <label class="block text-xs font-medium text-gray-500 mb-2">Font</label>
                            <div class="space-y-2">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-xs text-gray-400">Size</label>
                                        <input type="number" x-model.number="selectedElement.fontSize" @input="updateElement()"
                                               min="8" max="48"
                                               class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-lg">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-400">Weight</label>
                                        <select x-model="selectedElement.fontWeight" @change="updateElement()"
                                                class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-lg">
                                            <option value="normal">Normal</option>
                                            <option value="500">Medium</option>
                                            <option value="600">Semibold</option>
                                            <option value="bold">Bold</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400">Color</label>
                                    <div class="flex gap-2">
                                        <input type="color" x-model="selectedElement.color" @input="updateElement()"
                                               class="w-10 h-8 rounded border border-gray-200 cursor-pointer">
                                        <input type="text" x-model="selectedElement.color" @input="updateElement()"
                                               class="flex-1 px-2 py-1.5 text-sm border border-gray-200 rounded-lg font-mono">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Shape Properties -->
                        <div x-show="selectedElement?.type === 'shape'">
                            <label class="block text-xs font-medium text-gray-500 mb-2">Shape Style</label>
                            <div class="space-y-2">
                                <div>
                                    <label class="text-xs text-gray-400">Background</label>
                                    <div class="flex gap-2">
                                        <input type="color" x-model="selectedElement.bgColor" @input="updateElement()"
                                               class="w-10 h-8 rounded border border-gray-200 cursor-pointer">
                                        <input type="text" x-model="selectedElement.bgColor" @input="updateElement()"
                                               class="flex-1 px-2 py-1.5 text-sm border border-gray-200 rounded-lg font-mono">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-xs text-gray-400">Border Width</label>
                                        <input type="number" x-model.number="selectedElement.borderWidth" @input="updateElement()"
                                               min="0" max="10"
                                               class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-lg">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-400">Radius</label>
                                        <input type="number" x-model.number="selectedElement.borderRadius" @input="updateElement()"
                                               min="0" max="50"
                                               class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded-lg">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400">Border Color</label>
                                    <div class="flex gap-2">
                                        <input type="color" x-model="selectedElement.borderColor" @input="updateElement()"
                                               class="w-10 h-8 rounded border border-gray-200 cursor-pointer">
                                        <input type="text" x-model="selectedElement.borderColor" @input="updateElement()"
                                               class="flex-1 px-2 py-1.5 text-sm border border-gray-200 rounded-lg font-mono">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Line Properties -->
                        <div x-show="selectedElement?.type === 'line'">
                            <label class="block text-xs font-medium text-gray-500 mb-2">Line Style</label>
                            <div class="space-y-2">
                                <div>
                                    <label class="text-xs text-gray-400">Color</label>
                                    <div class="flex gap-2">
                                        <input type="color" x-model="selectedElement.color" @input="updateElement()"
                                               class="w-10 h-8 rounded border border-gray-200 cursor-pointer">
                                        <input type="text" x-model="selectedElement.color" @input="updateElement()"
                                               class="flex-1 px-2 py-1.5 text-sm border border-gray-200 rounded-lg font-mono">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Image Upload -->
                        <div x-show="selectedElement?.type === 'image'">
                            <label class="block text-xs font-medium text-gray-500 mb-2">Image Source</label>
                            <input type="file" accept="image/*" @change="handleImageUpload($event)"
                                   class="w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100">
                            <p class="text-xs text-gray-400 mt-1">Or enter URL:</p>
                            <input type="text" x-model="selectedElement.src" @input="updateElement()"
                                   placeholder="https://..."
                                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg mt-1">
                        </div>
                        
                        <!-- Layer Controls -->
                        <div class="pt-4 border-t border-gray-100">
                            <label class="block text-xs font-medium text-gray-500 mb-2">Layer Order</label>
                            <div class="flex gap-2">
                                <button @click="moveLayer('up')" class="flex-1 px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                    Bring Forward
                                </button>
                                <button @click="moveLayer('down')" class="flex-1 px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                    Send Back
                                </button>
                            </div>
                        </div>
                        
                        <!-- Delete Button -->
                        <div class="pt-4">
                            <button @click="deleteElement()" class="w-full px-3 py-2 text-sm bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors">
                                Delete Element
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Background Settings -->
                <div class="bg-white rounded-xl border border-gray-100 p-4 mt-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Background</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs text-gray-500">Type</label>
                            <select x-model="activeSide === 'front' ? frontBg.type : backBg.type" @change="updateBackground()"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg mt-1">
                                <option value="gradient">Gradient</option>
                                <option value="solid">Solid Color</option>
                                <option value="image">Image</option>
                            </select>
                        </div>
                        
                        <template x-if="(activeSide === 'front' ? frontBg.type : backBg.type) === 'solid'">
                            <div>
                                <label class="text-xs text-gray-500">Color</label>
                                <div class="flex gap-2 mt-1">
                                    <input type="color" 
                                           x-model="activeSide === 'front' ? frontBg.color : backBg.color" 
                                           @input="updateBackground()"
                                           class="w-10 h-8 rounded border border-gray-200 cursor-pointer">
                                    <input type="text" 
                                           x-model="activeSide === 'front' ? frontBg.color : backBg.color"
                                           @input="updateBackground()"
                                           class="flex-1 px-2 py-1.5 text-sm border border-gray-200 rounded-lg font-mono">
                                </div>
                            </div>
                        </template>
                        
                        <template x-if="(activeSide === 'front' ? frontBg.type : backBg.type) === 'gradient'">
                            <div class="space-y-2">
                                <div>
                                    <label class="text-xs text-gray-500">Start Color</label>
                                    <div class="flex gap-2 mt-1">
                                        <input type="color" 
                                               x-model="activeSide === 'front' ? frontBg.gradientStart : backBg.gradientStart"
                                               @input="updateBackground()"
                                               class="w-10 h-8 rounded border border-gray-200 cursor-pointer">
                                        <input type="text"
                                               x-model="activeSide === 'front' ? frontBg.gradientStart : backBg.gradientStart"
                                               @input="updateBackground()"
                                               class="flex-1 px-2 py-1.5 text-sm border border-gray-200 rounded-lg font-mono">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">End Color</label>
                                    <div class="flex gap-2 mt-1">
                                        <input type="color"
                                               x-model="activeSide === 'front' ? frontBg.gradientEnd : backBg.gradientEnd"
                                               @input="updateBackground()"
                                               class="w-10 h-8 rounded border border-gray-200 cursor-pointer">
                                        <input type="text"
                                               x-model="activeSide === 'front' ? frontBg.gradientEnd : backBg.gradientEnd"
                                               @input="updateBackground()"
                                               class="flex-1 px-2 py-1.5 text-sm border border-gray-200 rounded-lg font-mono">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Direction</label>
                                    <select x-model="activeSide === 'front' ? frontBg.gradientDirection : backBg.gradientDirection"
                                            @change="updateBackground()"
                                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg mt-1">
                                        <option value="to right">Left to Right</option>
                                        <option value="to left">Right to Left</option>
                                        <option value="to bottom">Top to Bottom</option>
                                        <option value="to top">Bottom to Top</option>
                                        <option value="to bottom right">Diagonal ↘</option>
                                        <option value="to bottom left">Diagonal ↙</option>
                                        <option value="to top right">Diagonal ↗</option>
                                        <option value="to top left">Diagonal ↖</option>
                                    </select>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Hidden form for saving -->
<form id="saveForm" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <input type="hidden" name="action" value="save_template">
    <input type="hidden" name="template_name" value="default">
    <input type="hidden" name="template_side" id="templateSide" value="front">
    <input type="hidden" name="template_data" id="templateData" value="">
</form>

<!-- Preview Modal -->
<div id="previewModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closePreviewModal()"></div>
        <div class="relative bg-white rounded-xl shadow-xl transform transition-all sm:max-w-2xl sm:w-full mx-auto">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Template Preview</h3>
                <button onclick="closePreviewModal()" class="p-1.5 text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div id="previewContent" class="p-6">
                <!-- Preview will be rendered here -->
            </div>
        </div>
    </div>
</div>

<script>
function idTemplateDesigner() {
    return {
        // Card dimensions (CR80 standard: 3.375" x 2.125" at 96 DPI ≈ 324 x 204)
        cardWidth: 324,
        cardHeight: 204,
        
        // Active side
        activeSide: 'front',
        
        // Selected element
        selectedElement: null,
        
        // Drag state
        isDragging: false,
        isResizing: false,
        dragOffset: { x: 0, y: 0 },
        
        // Background settings
        frontBg: {
            type: 'gradient',
            color: '#1a1a1a',
            gradientStart: '#1a1a1a',
            gradientEnd: '#CE1126',
            gradientDirection: 'to bottom right'
        },
        backBg: {
            type: 'gradient',
            color: '#CE1126',
            gradientStart: '#CE1126',
            gradientEnd: '#0038A8',
            gradientDirection: 'to bottom right'
        },
        
        // Elements on each side
        frontElements: <?php echo $frontTemplate ?: '[]'; ?>,
        backElements: <?php echo $backTemplate ?: '[]'; ?>,
        
        // Sample data for preview
        sampleData: {
            student_name: '<?php echo $sampleStudent ? e($sampleStudent['first_name'] . ' ' . $sampleStudent['last_name']) : 'Juan Dela Cruz'; ?>',
            first_name: '<?php echo $sampleStudent ? e($sampleStudent['first_name']) : 'Juan'; ?>',
            last_name: '<?php echo $sampleStudent ? e($sampleStudent['last_name']) : 'Dela Cruz'; ?>',
            lrn: '<?php echo $sampleStudent ? e($sampleStudent['lrn'] ?? $sampleStudent['student_id']) : '123456789012'; ?>',
            student_id: '<?php echo $sampleStudent ? e($sampleStudent['student_id']) : 'STU-001'; ?>',
            grade_section: '<?php echo $sampleStudent ? e($sampleStudent['class'] . ' - ' . ($sampleStudent['section'] ?? '')) : 'Grade 7 - Section A'; ?>',
            school_year: '<?php echo date('Y') . '-' . (date('Y') + 1); ?>',
            school_name: '<?php echo e(config('school_name', 'Sample School')); ?>',
            parent_name: '<?php echo $sampleStudent ? e($sampleStudent['parent_name'] ?? 'Parent Name') : 'Maria Dela Cruz'; ?>',
            parent_phone: '<?php echo $sampleStudent ? e($sampleStudent['parent_phone'] ?? '09XX XXX XXXX') : '0917 123 4567'; ?>',
            address: '<?php echo $sampleStudent ? e($sampleStudent['address'] ?? 'Address') : '123 Sample Street, City'; ?>'
        },
        
        // Element counter for unique IDs
        elementCounter: 0,
        
        init() {
            // Initialize element counter
            const allElements = [...this.frontElements, ...this.backElements];
            if (allElements.length > 0) {
                this.elementCounter = Math.max(...allElements.map(e => parseInt(e.id.split('-')[1]) || 0)) + 1;
            }
            
            // Add default elements if empty
            if (this.frontElements.length === 0) {
                this.addDefaultFrontElements();
            }
            if (this.backElements.length === 0) {
                this.addDefaultBackElements();
            }
            
            // Global mouse events for dragging
            document.addEventListener('mousemove', (e) => this.handleMouseMove(e));
            document.addEventListener('mouseup', () => this.handleMouseUp());
            
            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Delete' && this.selectedElement) {
                    this.deleteElement();
                }
                if (e.key === 'Escape') {
                    this.selectedElement = null;
                }
            });
        },
        
        addDefaultFrontElements() {
            this.frontElements = [
                { id: 'el-1', type: 'shape', x: 0, y: 0, width: 324, height: 40, bgColor: 'rgba(206,17,38,0.9)', borderWidth: 0, borderRadius: 0, borderColor: 'transparent', zIndex: 1 },
                { id: 'el-2', type: 'text', x: 162, y: 12, content: '<?php echo e(config('school_name', 'SCHOOL NAME')); ?>', fontSize: 11, fontWeight: 'bold', color: '#FCD116', zIndex: 10 },
                { id: 'el-3', type: 'photo', x: 15, y: 55, width: 70, height: 85, zIndex: 5 },
                { id: 'el-4', type: 'image', x: 240, y: 50, width: 65, height: 65, src: '', zIndex: 5 },
                { id: 'el-5', type: 'text', x: 15, y: 150, content: 'ID No.', fontSize: 9, fontWeight: 'normal', color: 'rgba(255,255,255,0.7)', zIndex: 10 },
                { id: 'el-6', type: 'field', x: 15, y: 162, field: 'lrn', fontSize: 12, fontWeight: 'bold', color: '#FCD116', zIndex: 10 },
                { id: 'el-7', type: 'text', x: 100, y: 55, content: 'Student Name', fontSize: 9, fontWeight: 'normal', color: 'rgba(255,255,255,0.7)', zIndex: 10 },
                { id: 'el-8', type: 'field', x: 100, y: 67, field: 'student_name', fontSize: 11, fontWeight: 'bold', color: '#ffffff', zIndex: 10 },
                { id: 'el-9', type: 'text', x: 100, y: 90, content: 'Grade & Section', fontSize: 9, fontWeight: 'normal', color: 'rgba(255,255,255,0.7)', zIndex: 10 },
                { id: 'el-10', type: 'field', x: 100, y: 102, field: 'grade_section', fontSize: 11, fontWeight: '600', color: '#FCD116', zIndex: 10 }
            ];
            this.elementCounter = 11;
        },
        
        addDefaultBackElements() {
            this.backElements = [
                { id: 'el-b1', type: 'qrcode', x: 112, y: 15, width: 100, height: 100, zIndex: 5 },
                { id: 'el-b2', type: 'text', x: 162, y: 125, content: 'S.Y.', fontSize: 10, fontWeight: 'normal', color: 'rgba(255,255,255,0.7)', zIndex: 10 },
                { id: 'el-b3', type: 'field', x: 162, y: 138, field: 'school_year', fontSize: 12, fontWeight: 'bold', color: '#FCD116', zIndex: 10 },
                { id: 'el-b4', type: 'shape', x: 15, y: 155, width: 294, height: 40, bgColor: 'rgba(255,255,255,0.1)', borderWidth: 0, borderRadius: 6, borderColor: 'transparent', zIndex: 1 },
                { id: 'el-b5', type: 'text', x: 162, y: 162, content: 'Contact Person', fontSize: 9, fontWeight: 'bold', color: '#FCD116', zIndex: 10 },
                { id: 'el-b6', type: 'field', x: 162, y: 175, field: 'parent_name', fontSize: 10, fontWeight: 'normal', color: '#ffffff', zIndex: 10 }
            ];
        },
        
        // Drag and drop from toolbox
        dragStart(event, type) {
            event.dataTransfer.setData('elementType', type);
        },
        
        dropElement(event, side) {
            event.preventDefault();
            const type = event.dataTransfer.getData('elementType');
            if (!type) return;
            
            const canvas = event.currentTarget;
            const rect = canvas.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;
            
            const newElement = this.createNewElement(type, x, y);
            
            if (side === 'front') {
                this.frontElements.push(newElement);
            } else {
                this.backElements.push(newElement);
            }
            
            this.selectedElement = newElement;
        },
        
        createNewElement(type, x, y) {
            const id = 'el-' + (++this.elementCounter);
            const baseElement = { id, type, x: Math.round(x), y: Math.round(y), zIndex: this.elementCounter };
            
            switch (type) {
                case 'text':
                    return { ...baseElement, content: 'Text Label', fontSize: 12, fontWeight: 'normal', color: '#ffffff' };
                case 'field':
                    return { ...baseElement, field: 'student_name', fontSize: 12, fontWeight: 'normal', color: '#ffffff' };
                case 'image':
                    return { ...baseElement, width: 60, height: 60, src: '' };
                case 'photo':
                    return { ...baseElement, width: 70, height: 85 };
                case 'qrcode':
                    return { ...baseElement, width: 80, height: 80 };
                case 'barcode':
                    return { ...baseElement, width: 150, height: 50 };
                case 'shape':
                    return { ...baseElement, width: 100, height: 50, bgColor: 'rgba(255,255,255,0.2)', borderWidth: 1, borderRadius: 4, borderColor: 'rgba(255,255,255,0.3)' };
                case 'line':
                    return { ...baseElement, width: 100, height: 2, color: 'rgba(255,255,255,0.5)' };
                default:
                    return baseElement;
            }
        },
        
        // Element selection
        selectElement(element) {
            this.selectedElement = element;
        },
        
        deselectAll(event) {
            if (event.target.id.includes('canvas')) {
                this.selectedElement = null;
            }
        },
        
        // Dragging elements
        startDrag(event, element) {
            if (this.isResizing) return;
            this.isDragging = true;
            this.selectedElement = element;
            
            const rect = event.target.getBoundingClientRect();
            this.dragOffset = {
                x: event.clientX - rect.left,
                y: event.clientY - rect.top
            };
        },
        
        handleMouseMove(event) {
            if (!this.selectedElement) return;
            
            const canvas = document.getElementById('canvas-' + this.activeSide);
            if (!canvas) return;
            const canvasRect = canvas.getBoundingClientRect();
            
            if (this.isDragging) {
                let newX = event.clientX - canvasRect.left - this.dragOffset.x;
                let newY = event.clientY - canvasRect.top - this.dragOffset.y;
                
                // Constrain to canvas
                newX = Math.max(0, Math.min(newX, this.cardWidth - 20));
                newY = Math.max(0, Math.min(newY, this.cardHeight - 20));
                
                this.selectedElement.x = Math.round(newX);
                this.selectedElement.y = Math.round(newY);
            }
            
            if (this.isResizing) {
                const newWidth = event.clientX - canvasRect.left - this.selectedElement.x;
                const newHeight = event.clientY - canvasRect.top - this.selectedElement.y;
                
                this.selectedElement.width = Math.max(20, Math.round(newWidth));
                this.selectedElement.height = Math.max(20, Math.round(newHeight));
            }
        },
        
        handleMouseUp() {
            this.isDragging = false;
            this.isResizing = false;
        },
        
        // Resizing
        startResize(event, element) {
            this.isResizing = true;
            this.selectedElement = element;
        },
        
        // Update element (for property changes)
        updateElement() {
            // Reactivity handles this automatically
        },
        
        // Delete element
        deleteElement() {
            if (!this.selectedElement) return;
            
            if (this.activeSide === 'front') {
                this.frontElements = this.frontElements.filter(e => e.id !== this.selectedElement.id);
            } else {
                this.backElements = this.backElements.filter(e => e.id !== this.selectedElement.id);
            }
            this.selectedElement = null;
        },
        
        // Layer management
        moveLayer(direction) {
            if (!this.selectedElement) return;
            
            const elements = this.activeSide === 'front' ? this.frontElements : this.backElements;
            const index = elements.findIndex(e => e.id === this.selectedElement.id);
            
            if (direction === 'up' && index < elements.length - 1) {
                [elements[index], elements[index + 1]] = [elements[index + 1], elements[index]];
            } else if (direction === 'down' && index > 0) {
                [elements[index], elements[index - 1]] = [elements[index - 1], elements[index]];
            }
            
            // Update zIndex
            elements.forEach((el, i) => el.zIndex = i + 1);
        },
        
        // Image upload
        handleImageUpload(event) {
            const file = event.target.files[0];
            if (!file || !this.selectedElement) return;
            
            const reader = new FileReader();
            reader.onload = (e) => {
                this.selectedElement.src = e.target.result;
            };
            reader.readAsDataURL(file);
        },
        
        // Background update
        updateBackground() {
            const canvas = document.getElementById('canvas-' + this.activeSide);
            if (!canvas) return;
            
            const bg = this.activeSide === 'front' ? this.frontBg : this.backBg;
            
            if (bg.type === 'solid') {
                canvas.style.background = bg.color;
            } else if (bg.type === 'gradient') {
                canvas.style.background = `linear-gradient(${bg.gradientDirection}, ${bg.gradientStart}, ${bg.gradientEnd})`;
            }
        },
        
        // Get field preview value
        getFieldPreview(field) {
            return this.sampleData[field] || '{' + field + '}';
        },
        
        // Style helpers
        getElementStyle(element) {
            return `left: ${element.x}px; top: ${element.y}px; z-index: ${element.zIndex || 1};`;
        },
        
        getTextStyle(element) {
            return `font-size: ${element.fontSize}px; font-weight: ${element.fontWeight}; color: ${element.color}; white-space: nowrap;`;
        },
        
        // Reset to default
        resetToDefault() {
            this.cardWidth = 324;
            this.cardHeight = 204;
            if (this.activeSide === 'front') {
                this.frontElements = [];
                this.addDefaultFrontElements();
            } else {
                this.backElements = [];
                this.addDefaultBackElements();
            }
            this.selectedElement = null;
        }
    };
}

// Save template
function saveTemplate() {
    const designer = Alpine.$data(document.querySelector('[x-data]'));
    
    // Save front template
    document.getElementById('templateSide').value = 'front';
    document.getElementById('templateData').value = JSON.stringify(designer.frontElements);
    
    // Clone form and submit for front
    const frontData = JSON.stringify({
        elements: designer.frontElements,
        background: designer.frontBg,
        cardWidth: designer.cardWidth,
        cardHeight: designer.cardHeight
    });
    
    // Save back template
    const backData = JSON.stringify({
        elements: designer.backElements,
        background: designer.backBg,
        cardWidth: designer.cardWidth,
        cardHeight: designer.cardHeight
    });
    
    // Use fetch to save both
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    Promise.all([
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `csrf_token=${csrfToken}&action=save_template&template_side=front&template_data=${encodeURIComponent(frontData)}`
        }),
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `csrf_token=${csrfToken}&action=save_template&template_side=back&template_data=${encodeURIComponent(backData)}`
        })
    ]).then(() => {
        alert('Template saved successfully!');
    }).catch(err => {
        alert('Error saving template: ' + err.message);
    });
}

// Preview template
function previewTemplate() {
    document.getElementById('previewModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    const designer = Alpine.$data(document.querySelector('[x-data]'));
    const previewContent = document.getElementById('previewContent');
    
    previewContent.innerHTML = `
        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <div>
                <p class="text-sm text-gray-500 mb-2">Front</p>
                <div id="preview-front" class="rounded-xl overflow-hidden shadow-lg" 
                     style="width: ${designer.cardWidth}px; height: ${designer.cardHeight}px; background: linear-gradient(${designer.frontBg.gradientDirection}, ${designer.frontBg.gradientStart}, ${designer.frontBg.gradientEnd});">
                </div>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-2">Back</p>
                <div id="preview-back" class="rounded-xl overflow-hidden shadow-lg"
                     style="width: ${designer.cardWidth}px; height: ${designer.cardHeight}px; background: linear-gradient(${designer.backBg.gradientDirection}, ${designer.backBg.gradientStart}, ${designer.backBg.gradientEnd});">
                </div>
            </div>
        </div>
    `;
    
    // Render elements
    renderPreviewElements('preview-front', designer.frontElements, designer.sampleData);
    renderPreviewElements('preview-back', designer.backElements, designer.sampleData);
}

function renderPreviewElements(containerId, elements, sampleData) {
    const container = document.getElementById(containerId);
    container.style.position = 'relative';
    
    elements.forEach(el => {
        const div = document.createElement('div');
        div.style.cssText = `position: absolute; left: ${el.x}px; top: ${el.y}px; z-index: ${el.zIndex || 1};`;
        
        switch (el.type) {
            case 'text':
                div.innerHTML = `<span style="font-size: ${el.fontSize}px; font-weight: ${el.fontWeight}; color: ${el.color}; white-space: nowrap;">${el.content}</span>`;
                break;
            case 'field':
                div.innerHTML = `<span style="font-size: ${el.fontSize}px; font-weight: ${el.fontWeight}; color: ${el.color}; white-space: nowrap;">${sampleData[el.field] || ''}</span>`;
                break;
            case 'photo':
                div.innerHTML = `<div style="width: ${el.width}px; height: ${el.height}px; background: rgba(255,255,255,0.1); border: 2px solid #CE1126; border-radius: 4px; display: flex; align-items: center; justify-content: center;"><svg style="width: 30px; height: 30px; color: #CE1126;" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></div>`;
                break;
            case 'image':
                if (el.src) {
                    div.innerHTML = `<img src="${el.src}" style="width: ${el.width}px; height: ${el.height}px; object-fit: contain; border-radius: 4px;">`;
                } else {
                    div.innerHTML = `<div style="width: ${el.width}px; height: ${el.height}px; background: rgba(255,255,255,0.2); border: 2px dashed rgba(255,255,255,0.4); border-radius: 4px; display: flex; align-items: center; justify-content: center;"><span style="color: rgba(255,255,255,0.6); font-size: 10px;">Logo</span></div>`;
                }
                break;
            case 'qrcode':
                div.innerHTML = `<div style="width: ${el.width}px; height: ${el.height}px; background: white; border-radius: 4px; padding: 8px;"><svg viewBox="0 0 100 100" style="width: 100%; height: 100%;"><rect x="10" y="10" width="30" height="30" fill="black"/><rect x="15" y="15" width="20" height="20" fill="white"/><rect x="20" y="20" width="10" height="10" fill="black"/><rect x="60" y="10" width="30" height="30" fill="black"/><rect x="65" y="15" width="20" height="20" fill="white"/><rect x="70" y="20" width="10" height="10" fill="black"/><rect x="10" y="60" width="30" height="30" fill="black"/><rect x="15" y="65" width="20" height="20" fill="white"/><rect x="20" y="70" width="10" height="10" fill="black"/></svg></div>`;
                break;
            case 'shape':
                div.innerHTML = `<div style="width: ${el.width}px; height: ${el.height}px; background-color: ${el.bgColor}; border-radius: ${el.borderRadius}px; border: ${el.borderWidth}px solid ${el.borderColor};"></div>`;
                break;
            case 'line':
                div.innerHTML = `<div style="width: ${el.width}px; height: ${el.height}px; background-color: ${el.color};"></div>`;
                break;
        }
        
        container.appendChild(div);
    });
}

function closePreviewModal() {
    document.getElementById('previewModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Close on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePreviewModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
