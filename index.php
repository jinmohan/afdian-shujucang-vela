<?php
// server.php

// 初始化 SQLite3 数据库，作为轻量级中转存储
$dbPath = __DIR__ . '/sessions.db';
$db = new SQLite3($dbPath);
$db->exec("CREATE TABLE IF NOT EXISTS sessions (sid TEXT PRIMARY KEY, payload TEXT, created_at INTEGER)");

// 垃圾回收：清理超过 5 分钟（300秒）的未完成会话
$timeNow = time();
$db->exec("DELETE FROM sessions WHERE created_at < " . ($timeNow - 300));

// 路由控制
$action = $_GET['action'] ?? 'view';

// 1. 创建会话接口
if ($action === 'create') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    $sid = bin2hex(random_bytes(16)); // 生成随机的 32 字符 Session ID
    $stmt = $db->prepare("INSERT INTO sessions (sid, created_at) VALUES (:sid, :time)");
    $stmt->bindValue(':sid', $sid, SQLITE3_TEXT);
    $stmt->bindValue(':time', $timeNow, SQLITE3_INTEGER);
    $stmt->execute();
    echo json_encode(['sid' => $sid]);
    exit;
}

// 2. 接收端轮询接口
if ($action === 'poll') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    $sid = $_GET['sid'] ?? '';
    $stmt = $db->prepare("SELECT payload FROM sessions WHERE sid = :sid");
    $stmt->bindValue(':sid', $sid, SQLITE3_TEXT);
    $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if ($res && !empty($res['payload'])) {
        echo json_encode(['status' => 'success', 'payload' => $res['payload']]);
        // 数据一旦被取走，立即销毁（阅后即焚）
        $delStmt = $db->prepare("DELETE FROM sessions WHERE sid = :sid");
        $delStmt->bindValue(':sid', $sid, SQLITE3_TEXT);
        $delStmt->execute();
    } else {
        echo json_encode(['status' => 'waiting']);
    }
    exit;
}

// 3. 前端提交加密数据接口
if ($action === 'submit') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $sid = $data['sid'] ?? '';
    $payload = $data['payload'] ?? '';
    
    if ($sid && $payload) {
        $stmt = $db->prepare("UPDATE sessions SET payload = :payload WHERE sid = :sid");
        $stmt->bindValue(':payload', $payload, SQLITE3_TEXT);
        $stmt->bindValue(':sid', $sid, SQLITE3_TEXT);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Invalid parameters']);
    }
    exit;
}

// 4. 若未命中 API，则渲染前端输入页面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <title>爱发电数据仓COOKIE同步</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700;900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>

    <style>
        /* 借鉴大厂级 UI 变量定义 */
        :root {
            --bg-color: #f4f5f7; /* 更具质感的冷灰背景 */
            --card-bg: #FFFFFF;
            --text-color: #1c1b1f;
            --title-color: #1a1a1a;
            --muted-color: #5f6368;
            --primary-color: #1a1a1a;
            --success-color: #198754;
            --error-color: #DC3545;
            
            /* 加强圆角：MD3 级别大圆角 */
            --shape-corner-xl: 28px;
            --shape-corner-lg: 20px;
            --shape-corner-md: 16px;
            
            /* 高级丝滑缓动动画 */
            --transition-smooth: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Noto Sans SC', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
            overscroll-behavior: none;
        }

        /* 初始化隐藏，交由 GSAP 控制入场 */
        .gsap-reveal {
            opacity: 0;
            visibility: hidden;
        }

        h1 {
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--title-color);
            margin-bottom: 32px;
            text-align: center;
            letter-spacing: -0.5px;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: var(--shape-corner-xl);
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            width: 100%;
            max-width: 450px;
            padding: 32px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            transition: var(--transition-smooth);
        }

        .status-box {
            background-color: #F8F9FA;
            border-radius: var(--shape-corner-lg);
            padding: 20px;
            margin-bottom: 25px;
            font-family: monospace;
            line-height: 1.6;
            transition: var(--transition-smooth);
            border: 1px solid transparent;
        }
        .status-box:hover {
            background-color: #ffffff;
            border-color: #e0e0e0;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            transform: translateY(-2px);
        }
        
        .status-box p { margin: 0; font-size: 14px; }
        .status-box .label { color: var(--muted-color); margin-right: 8px; }
        .status-box .value { color: var(--text-color); font-weight: 600; }
        
        .status-box .success-msg {
            display: flex;
            align-items: center;
            color: var(--success-color);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .status-box .success-icon { margin-right: 8px; }

        .helper-link {
            display: block;
            text-align: right;
            color: var(--muted-color);
            margin-bottom: 15px;
            font-size: 14px;
            text-decoration: none;
            transition: var(--transition-smooth);
            font-weight: 500;
        }
        .helper-link:hover { color: var(--primary-color); }

        textarea {
            background-color: transparent;
            color: var(--text-color);
            border: 2px solid #E1E1E1;
            padding: 20px;
            height: 120px;
            font-family: inherit;
            border-radius: var(--shape-corner-lg);
            margin-bottom: 25px;
            resize: none;
            outline: none;
            transition: var(--transition-smooth);
            box-sizing: border-box;
            font-size: 15px;
        }
        textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        button#submitBtn {
            background-color: var(--primary-color);
            color: #FFFFFF;
            border: none;
            padding: 18px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            border-radius: 100px; /* 极致的全圆角药丸按钮 */
            transition: var(--transition-smooth);
            position: relative;
            overflow: hidden;
        }
        button#submitBtn:hover {
            background-color: #000000;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        button#submitBtn:active { transform: scale(0.97); }
        button#submitBtn:disabled {
            background-color: #D6D6D6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .tutorial-box {
            margin-top: 30px;
            background-color: var(--card-bg);
            border: 1px solid #E1E1E1;
            border-radius: var(--shape-corner-xl);
            padding: 32px;
            width: 100%;
            max-width: 450px;
            box-sizing: border-box;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
            transition: var(--transition-smooth);
        }
        .tutorial-box:hover { box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06); }
        
        .tutorial-box h2 {
            font-size: 18px;
            color: var(--primary-color);
            margin-top: 0;
            padding-bottom: 15px;
            font-weight: 700;
            border-bottom: 2px dashed #eee;
        }
        .tutorial-box p, .tutorial-box li {
            font-size: 14px;
            color: var(--muted-color);
            line-height: 1.8;
        }
        .tutorial-box ol { padding-left: 20px; margin-bottom: 25px; }
        .tutorial-box code {
            background-color: #f4f5f7;
            color: var(--primary-color);
            padding: 4px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 13px;
            font-weight: 600;
        }
        
        .preview-btn {
            background-color: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            border-radius: 100px;
            margin-top: 10px;
            width: 100%;
            cursor: pointer;
            transition: var(--transition-smooth);
        }
        .preview-btn:hover { background-color: var(--primary-color); color: #fff; }
        .preview-btn:active { transform: scale(0.97); }
        
        #tutorialImg {
            display: none;
            width: 100%;
            margin-top: 20px;
            border-radius: var(--shape-corner-md);
            border: 1px solid #E1E1E1;
            opacity: 0; /* 用于 GSAP 动画 */
        }

        /* 模态框样式优化 */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px); /* 背景毛玻璃效果 */
            align-items: center;
            justify-content: center;
            opacity: 0; /* 用于动画 */
        }
        .modal-content {
            background-color: var(--card-bg);
            border-radius: var(--shape-corner-xl);
            padding: 35px 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            width: 90%;
            max-width: 380px;
            display: flex;
            flex-direction: column;
            align-items: center;
            transform: scale(0.9); /* 用于动画 */
        }
        .modal-title {
            font-size: 20px;
            font-weight: 900;
            color: var(--title-color);
            margin-bottom: 20px;
        }
        .modal-body {
            display: flex;
            align-items: center;
            color: var(--muted-color);
            margin-bottom: 30px;
            font-size: 16px;
            text-align: center;
        }
        .modal-icon { margin-right: 10px; font-size: 24px; }
        .modal-confirm-btn {
            background-color: var(--primary-color);
            color: #FFFFFF;
            border: none;
            padding: 16px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            border-radius: 100px;
            width: 100%;
            transition: var(--transition-smooth);
        }
        .modal-confirm-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .modal-confirm-btn:active { transform: scale(0.97); }
    </style>
</head>
<body>
    <h1 class="gsap-reveal">爱发电数据仓COOKIE同步器</h1>
    
    <div class="card gsap-reveal">
        <div class="status-box">
            <div class="success-msg">
                <span class="success-icon">&#9989;</span> 同步器配置初始化完成！
            </div>
            
            <p><span class="value">数据一旦被取走，立即销毁（阅后即焚）</span></p>
            <p><span class="value">请在5分钟内完成输入！</span></p>
        </div>

        <a href="#tutorial" class="helper-link">如何获取 Cookie？</a>

        <textarea id="cookieInput" placeholder="仅需在此处输入 auth_token 的值..."></textarea>
        <button id="submitBtn" onclick="handleFormSubmit()">导入设备</button>
    </div>

    <div class="tutorial-box gsap-reveal" id="tutorial">
        <h2>如何获取 auth_token ?</h2>
        <ol>
            <li>在电脑浏览器打开 <strong>爱发电</strong> 网页并登录。</li>
            <li>按键盘 <code>F12</code> 打开开发者工具。</li>
            <li>在顶部标签栏找到 <strong>Application (应用)</strong> 或 <strong>存储</strong>。</li>
            <li>在左侧菜单展开 <strong>Cookies (Cookie)</strong>，点击爱发电的网址。</li>
            <li>在右侧列表中找到 Name 为 <code>auth_token</code> 的行。</li>
            <li>双击它的 <strong>Value (值)</strong> 并复制，粘贴到上方输入框中。</li>
        </ol>
        <button class="preview-btn" onclick="toggleTutorial()">预览图片版教程</button>
        <img id="tutorialImg" src="tutorial.png" alt="获取Cookie图文教程">
    </div>

    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-title">系统提示</div>
            <div id="modalBody" class="modal-body"></div>
            <button class="modal-confirm-btn" onclick="closeModal()">确定</button>
        </div>
    </div>

    <script>
        // =========================================
        // GSAP 视觉入场动画 (页面加载后触发)
        // =========================================
        window.addEventListener("DOMContentLoaded", () => {
            gsap.set(".gsap-reveal", { visibility: "visible" });
            gsap.fromTo(".gsap-reveal", 
                { opacity: 0, y: 40 }, 
                { 
                    opacity: 1, 
                    y: 0, 
                    duration: 0.8, 
                    stagger: 0.15, 
                    ease: "back.out(1.2)" 
                }
            );
        });

        // 核心功能脚本
        const urlParams = new URLSearchParams(window.location.search);
        const sid = urlParams.get('sid');

        const cookieInputEl = document.getElementById('cookieInput');
        const btnEl = document.getElementById('submitBtn');
        const modalEl = document.getElementById('statusModal');
        const modalContentEl = document.querySelector('.modal-content');
        const modalBodyEl = document.getElementById('modalBody');

        if (!sid) {
            showModal('error', '无效的链接：缺少 SID');
            btnEl.disabled = true;
            cookieInputEl.disabled = true;
        }

        function handleFormSubmit() {
            let rawData = cookieInputEl.value.trim();
            if (!rawData) {
                // 如果为空，可以用一个轻微震动动画提示用户
                gsap.fromTo(cookieInputEl, { x: -5 }, { x: 5, duration: 0.05, yoyo: true, repeat: 5, onComplete: () => gsap.set(cookieInputEl, {x:0}) });
                return;
            }

            if (rawData.startsWith('auth_token=')) {
                rawData = rawData.substring(11);
            }
            
            const payloadData = 'auth_token=' + rawData;

            btnEl.innerText = '正在导入设备...';
            btnEl.disabled = true;

            fetch('?action=submit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sid: sid, payload: payloadData })
            })
            .then(res => res.json())
            .then(res => {
                btnEl.innerText = '导入设备';
                btnEl.disabled = false;

                if (res.status === 'success') {
                    showModal('success', '成功：数据已导入设备！');
                    cookieInputEl.value = ''; 
                } else {
                    throw new Error(res.msg);
                }
            })
            .catch(err => {
                btnEl.innerText = '导入设备';
                btnEl.disabled = false;
                showModal('error', '导入失败：请检查网络');
            });
        }

        // 引入 GSAP 弹窗动画
        function showModal(type, msg) {
            modalBodyEl.innerHTML = ''; 
            const iconSpan = document.createElement('span');
            iconSpan.classList.add('modal-icon');
            
            if (type === 'success') {
                iconSpan.innerHTML = '&#9989;'; 
                iconSpan.style.color = 'var(--success-color)';
            } else if (type === 'error') {
                iconSpan.innerHTML = '&#10060;'; 
            }
            
            const msgSpan = document.createElement('span');
            msgSpan.innerText = msg;
            
            modalBodyEl.appendChild(iconSpan);
            modalBodyEl.appendChild(msgSpan);
            
            // GSAP 显示动画
            modalEl.style.display = 'flex';
            gsap.to(modalEl, { opacity: 1, duration: 0.3, ease: "power2.out" });
            gsap.to(modalContentEl, { scale: 1, duration: 0.4, ease: "back.out(1.5)" });
        }

        function closeModal() {
            // GSAP 隐藏动画
            gsap.to(modalContentEl, { scale: 0.9, duration: 0.2, ease: "power2.in" });
            gsap.to(modalEl, { opacity: 0, duration: 0.2, ease: "power2.in", onComplete: () => {
                modalEl.style.display = 'none';
            }});
        }

        function toggleTutorial() {
            const imgEl = document.getElementById('tutorialImg');
            if (imgEl.style.display === 'none' || imgEl.style.display === '') {
                imgEl.style.display = 'block';
                gsap.fromTo(imgEl, { height: 0, opacity: 0 }, { height: "auto", opacity: 1, duration: 0.4, ease: "power2.out" });
            } else {
                gsap.to(imgEl, { opacity: 0, height: 0, duration: 0.3, ease: "power2.in", onComplete: () => imgEl.style.display = 'none' });
            }
        }
    </script>
</body>
</html>