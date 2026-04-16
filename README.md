# 爱发电数据仓 (Afdian Data Dashboard)

一个专为爱发电（Afdian）创作者设计的轻量化数据监控终端。支持实时销售统计、商品管理及动态安全认证，适配穿戴设备或嵌入式屏幕显示。



## ✨ 核心特性

- 🔐 **动态设备认证**：手机端扫码即可将 Cookie 安全推送到设备。
- 📊 **可视化看板**：实时展示总销售额、总销量，内置数字滚动动画及上浮进场特效。
- 📉 **销量 Top 统计**：基于 `chart` 组件的柱状图，直观分析热门商品占比。
- 📦 **商品精细管理**：支持查看所有赞助方案及商品状态（在售/下架），点击可切换查看销量、收入及扣除手续费后的预计利润。
- 🔄 **自愈式登录拦截**：当检测到 Cookie 失效（提示“请重新登陆”等）时，系统会自动擦除本地失效记录并重定向至认证页面，保证设备长效运行不卡死。
- 💡 **极速性能优化**：采用分片数据处理逻辑，即使商品数量众多也能流畅加载，避免设备 OOM（内存溢出）。

## 🚀 快速开始


###  后端部署
你需要部署扫码通讯中转站（示例中使用 `https://mfapi.mfoa.top/...`）。
- 将 `index.php` 部署至你的服务器。
- 该中转站负责 `sid` 的生成、心跳轮询以及 `payload` (Cookie) 的数据中转。


## 🛡️ 安全说明
- **隐私保护**：本程序不会在云端存储您的 Cookie。Cookie 仅在扫码过程中通过中转服务器内存瞬时传递，并最终存储在设备本地的 `Storage` 中。


## 📸 界面预览

![UI](https://mfapi.mfoa.top/image/afdianshujucang/ui.png "UI")


## 快速上手

### 1. 开发

```
npm install
npm run start
```

### 2. 构建

```
npm run build
npm run release
```

### 3. 调试

```
npm run watch
```

## 了解更多

你可以通过小米的[官方文档](https://iot.mi.com/vela/quickapp)熟悉和了解快应用。

## 📄 开源协议 (License)

本项目遵循 [Apache-2.0 License](http://www.apache.org/licenses/) 开源协议，要求分发和修改的同时也公开源码，且使用相同的许可协议。欢迎 Fork 学习与提交 PR 改进！

---


# Develop By: MatrixFive Studio
![MatrixFive Studio](https://mfis.one/heartrate/MatrixFiveStudio.png "MatrixFive Studio")

---