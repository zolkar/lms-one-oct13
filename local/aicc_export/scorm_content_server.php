<?php

// Simple SCORM content server that serves files directly
// This approach uses a different URL structure to handle file requests

// Get parameters
$cmid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$file = isset($_GET['file']) ? $_GET['file'] : '';

if (!$cmid) {
    http_response_code(400);
    echo "Error: Missing course module ID";
    exit;
}

// Database connection parameters
$dbhost = 'moodle_db';
$dbname = 'lms_one';
$dbuser = 'root';
$dbpass = 'example';

try {
    $db = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo "Database connection error: " . $e->getMessage();
    exit;
}

// Get the course module
$stmt = $db->prepare("SELECT * FROM mdl_course_modules WHERE id = ?");
$stmt->execute([$cmid]);
$cm = $stmt->fetch(PDO::FETCH_OBJ);

if (!$cm) {
    http_response_code(404);
    echo "Error: Course module not found";
    exit;
}

// Get the SCORM activity
$stmt = $db->prepare("SELECT * FROM mdl_scorm WHERE id = ?");
$stmt->execute([$cm->instance]);
$scorm = $stmt->fetch(PDO::FETCH_OBJ);

if (!$scorm) {
    http_response_code(404);
    echo "Error: SCORM activity not found";
    exit;
}

// Get the first SCO for this SCORM activity that has a launch path
$stmt = $db->prepare("SELECT * FROM mdl_scorm_scoes WHERE scorm = ? AND launch IS NOT NULL AND launch != '' ORDER BY id LIMIT 1");
$stmt->execute([$scorm->id]);
$sco = $stmt->fetch(PDO::FETCH_OBJ);

if (!$sco) {
    http_response_code(404);
    echo "Error: No SCO found in SCORM activity";
    exit;
}

// Get the context ID for the course module
$stmt = $db->prepare("SELECT * FROM mdl_context WHERE contextlevel = 70 AND instanceid = ?");
$stmt->execute([$cmid]);
$context = $stmt->fetch(PDO::FETCH_OBJ);

if (!$context) {
    http_response_code(404);
    echo "Error: Context not found for course module";
    exit;
}

// Get the SCORM package file
$stmt = $db->prepare("SELECT * FROM mdl_files WHERE component = 'mod_scorm' AND filearea = 'package' AND itemid = 0 AND contextid = ?");
$stmt->execute([$context->id]);
$package_file = $stmt->fetch(PDO::FETCH_OBJ);

if (!$package_file) {
    http_response_code(404);
    echo "Error: SCORM package not found";
    exit;
}

// Create a persistent directory for this SCORM package
$persistent_dir = '/tmp/scorm_package_' . $cmid;
if (!is_dir($persistent_dir)) {
    if (!mkdir($persistent_dir, 0755, true)) {
        http_response_code(500);
        echo "Error: Could not create persistent directory";
        exit;
    }
    
    // Extract the ZIP file
    $content_hash = $package_file->contenthash;
    $zip_path = '/var/www/lms-one-data/filedir/' . substr($content_hash, 0, 2) . '/' . substr($content_hash, 2, 2) . '/' . $content_hash;
    if (!file_exists($zip_path)) {
        http_response_code(404);
        echo "Error: Package file not found on disk. Path: " . $zip_path;
        exit;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== TRUE) {
        http_response_code(500);
        echo "Error: Could not open package file";
        exit;
    }
    
    $zip->extractTo($persistent_dir);
    $zip->close();
}

// If a specific file is requested, serve it
if (!empty($file)) {
    // Remove any query parameters from the filename (for cache busting)
    $clean_file = $file;
    if (strpos($file, '?') !== false) {
        $clean_file = substr($file, 0, strpos($file, '?'));
    }
    
    $target_path = $persistent_dir . '/' . $clean_file;
    
    if (file_exists($target_path) && is_file($target_path)) {
        // Determine content type
        $extension = strtolower(pathinfo($target_path, PATHINFO_EXTENSION));
        $content_types = [
            'html' => 'text/html',
            'htm' => 'text/html',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'swf' => 'application/x-shockwave-flash',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav'
        ];
        
        $content_type = isset($content_types[$extension]) ? $content_types[$extension] : 'application/octet-stream';
        
        // Set headers
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . filesize($target_path));
        
        // Serve the file
        readfile($target_path);
        exit;
    } else {
        http_response_code(404);
        echo "Error: File not found: " . $clean_file;
        exit;
    }
}

// Serve the main SCORM content page
$target_file = $sco->launch ?: 'index.html';
$target_path = $persistent_dir . '/' . $target_file;
if (!file_exists($target_path)) {
    // Try to find the file in subdirectories
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($persistent_dir));
    foreach ($iterator as $file_obj) {
        if ($file_obj->isFile() && basename($file_obj->getPathname()) === basename($target_file)) {
            $target_path = $file_obj->getPathname();
            break;
        }
    }
}

if (!file_exists($target_path)) {
    http_response_code(404);
    echo "Error: File not found in package: " . $target_file;
    exit;
}

// Read the main HTML file and modify it to use correct URLs
$html_content = file_get_contents($target_path);

// Add base tag to ensure all relative URLs are resolved correctly
$base_url = '/lms-one/local/aicc_export/scorm_content_server.php?id=' . $cmid;
if (!empty($student_id)) {
    $base_url .= '&student_id=' . urlencode($student_id);
}
$base_url .= '&file=res/';

// Inject JavaScript to intercept and rewrite resource requests
$resource_rewrite_script = '
<script>
// Intercept all resource requests and rewrite them to use our file server
(function() {
    var originalFetch = window.fetch;
    var originalXHROpen = XMLHttpRequest.prototype.open;
    var originalXHRSend = XMLHttpRequest.prototype.send;
    
    var baseUrl = "' . $base_url . '";
    var currentUrl = window.location.href;
    var basePath = "/lms-one/local/aicc_export/";
    
    function rewriteUrl(url) {
        if (typeof url === "string") {
            // Handle relative URLs (no protocol, no leading slash)
            if (!url.startsWith("http") && !url.startsWith("/")) {
                return baseUrl + url;
            }
            // Handle absolute URLs that point to our export directory
            if (url.startsWith(basePath)) {
                var relativePath = url.substring(basePath.length);
                return baseUrl + relativePath;
            }
            // Handle URLs that start with /lms-one/local/aicc_export/ (absolute paths)
            if (url.startsWith("/lms-one/local/aicc_export/")) {
                var relativePath = url.substring("/lms-one/local/aicc_export/".length);
                return baseUrl + relativePath;
            }
        }
        return url;
    }
    
    // Rewrite fetch requests
    window.fetch = function(url, options) {
        console.log("Fetch request:", url);
        url = rewriteUrl(url);
        console.log("Rewritten to:", url);
        return originalFetch.call(this, url, options);
    };
    
    // Rewrite XMLHttpRequest requests
    XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
        console.log("XHR request:", url);
        url = rewriteUrl(url);
        console.log("XHR rewritten to:", url);
        return originalXHROpen.call(this, method, url, async, user, password);
    };
    
    // Also intercept dynamic script loading
    var originalCreateElement = document.createElement;
    document.createElement = function(tagName) {
        var element = originalCreateElement.call(this, tagName);
        if (tagName.toLowerCase() === "script" || tagName.toLowerCase() === "link") {
            var originalSetAttribute = element.setAttribute;
            element.setAttribute = function(name, value) {
                if (name === "src" || name === "href") {
                    value = rewriteUrl(value);
                }
                return originalSetAttribute.call(this, name, value);
            };
        }
        return element;
    };
    
    // Also intercept img src changes
    var originalImageSrc = Object.getOwnPropertyDescriptor(HTMLImageElement.prototype, "src");
    if (originalImageSrc) {
        Object.defineProperty(HTMLImageElement.prototype, "src", {
            get: originalImageSrc.get,
            set: function(value) {
                value = rewriteUrl(value);
                return originalImageSrc.set.call(this, value);
            }
        });
    }
})();
</script>';

// Insert base tag and resource rewrite script after <head> tag
$html_content = preg_replace('/<head[^>]*>/i', '$0<base href="' . $base_url . '">' . $resource_rewrite_script, $html_content);

// Create session ID for HACP communication
$session_id = 'SCORM_' . $cmid . '_' . time();

// Create session record in database for HACP tracking
try {
    $session_record = new \stdClass();
    $session_record->session_id = $session_id;
    $session_record->scormid = $scorm->id;
    $session_record->scoid = $sco->id;
    $session_record->userid = 0; // External student, not Moodle user
    $session_record->status = 'active';
    $session_record->created_at = time();
    $session_record->last_activity_at = time();
    $session_record->student_id = $student_id;
    
    $stmt = $db->prepare("INSERT INTO mdl_local_aicc_export_sessions (session_id, scormid, scoid, userid, status, created_at, last_activity_at, student_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $session_record->session_id,
        $session_record->scormid,
        $session_record->scoid,
        $session_record->userid,
        $session_record->status,
        $session_record->created_at,
        $session_record->last_activity_at,
        $session_record->student_id
    ]);
    
    error_log("Created HACP session: " . $session_id . " for student: " . $student_id . " SCORM: " . $scorm->id . " SCO: " . $sco->id);
} catch (Exception $e) {
    error_log("Failed to create HACP session: " . $e->getMessage());
}

// Set up HACP communication URL for the SCORM content
$hacp_base_url = '/lms-one/local/aicc_export/hacp_standalone.php?session_id=' . $session_id;

// Create base URL for file requests
$file_base_url = '/lms-one/local/aicc_export/scorm_content_server.php?id=' . $cmid;
if (!empty($student_id)) {
    $file_base_url .= '&student_id=' . urlencode($student_id);
}

// Modify the HTML content to use correct URLs for resources
$html_content = preg_replace_callback('/src=["\']([^"\']+)["\']/', function($matches) use ($file_base_url, $target_file) {
    $src = $matches[1];
    // If it's a relative URL, make it absolute
    if (!preg_match('/^https?:\/\//', $src) && !preg_match('/^\//', $src)) {
        // Get the directory of the current file
        $current_dir = dirname($target_file);
        if ($current_dir === '.') {
            $current_dir = '';
        } else {
            $current_dir .= '/';
        }
        
        // Debug logging
        error_log("SCORM URL Rewrite Debug:");
        error_log("Original src: " . $src);
        error_log("Target file: " . $target_file);
        error_log("Current dir: " . $current_dir);
        
        // Handle files with query parameters (like browsersupport.js?CA085A4C)
        if (strpos($src, '?') !== false) {
            list($file_path, $query_string) = explode('?', $src, 2);
            $full_path = $current_dir . $file_path;
            $src = $file_base_url . '&file=' . urlencode($full_path) . '&' . $query_string;
        } else {
            $full_path = $current_dir . $src;
            $src = $file_base_url . '&file=' . urlencode($full_path);
        }
        
        error_log("Full path: " . $full_path);
        error_log("Final src: " . $src);
    }
    return 'src="' . $src . '"';
}, $html_content);

$html_content = preg_replace_callback('/href=["\']([^"\']+)["\']/', function($matches) use ($file_base_url, $target_file) {
    $href = $matches[1];
    // If it's a relative URL, make it absolute
    if (!preg_match('/^https?:\/\//', $href) && !preg_match('/^\//', $href)) {
        // Get the directory of the current file
        $current_dir = dirname($target_file);
        if ($current_dir === '.') {
            $current_dir = '';
        } else {
            $current_dir .= '/';
        }
        
        // Handle files with query parameters
        if (strpos($href, '?') !== false) {
            list($file_path, $query_string) = explode('?', $href, 2);
            $full_path = $current_dir . $file_path;
            $href = $file_base_url . '&file=' . urlencode($full_path) . '&' . $query_string;
        } else {
            $full_path = $current_dir . $href;
            $href = $file_base_url . '&file=' . urlencode($full_path);
        }
    }
    return 'href="' . $href . '"';
}, $html_content);

// Add HACP communication script
$hacp_script = '
<script>
// HACP communication setup
var hacpBaseUrl = "' . $hacp_base_url . '";
var sessionId = "' . $session_id . '";
var studentId = "' . htmlspecialchars($student_id) . '";

// Global HACP communication functions for SCORM content
window.hacpGetParam = function() {
    var url = hacpBaseUrl + "&command=getparam";
    if (studentId) {
        url += "&AICC_SID=" + encodeURIComponent(studentId);
    }
    return url;
};

window.hacpPutParam = function(aiccData) {
    var url = hacpBaseUrl + "&command=putparam";
    if (studentId) {
        url += "&AICC_SID=" + encodeURIComponent(studentId);
    }
    if (aiccData) {
        url += "&aicc_data=" + encodeURIComponent(aiccData);
    }
    return url;
};

window.hacpExitAu = function() {
    var url = hacpBaseUrl + "&command=exitau";
    if (studentId) {
        url += "&AICC_SID=" + encodeURIComponent(studentId);
    }
    return url;
};

console.log("SCORM Content Server Ready");
console.log("Session ID:", sessionId);
console.log("Student ID:", studentId);
console.log("HACP Functions: hacpGetParam(), hacpPutParam(data), hacpExitAu()");
</script>';

// Insert the HACP script before the closing body tag
$html_content = str_replace('</body>', $hacp_script . '</body>', $html_content);

// Set headers
header('Content-Type: text/html; charset=UTF-8');

// Serve the modified HTML content
echo $html_content;