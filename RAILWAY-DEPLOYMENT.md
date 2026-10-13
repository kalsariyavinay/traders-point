# 🚂 Railway.app Deployment Guide - Traders Point Academy

Railway.app is the **easiest way** to deploy Laravel applications. It automatically detects Laravel and sets everything up.

---

## ✅ **Why Railway?**

- ✅ **Auto-detects Laravel** (no configuration needed)
- ✅ **Free $5/month credit** (enough for testing)
- ✅ **Built-in MySQL** database
- ✅ **GitHub integration** (auto-deploy on push)
- ✅ **Environment variables** management
- ✅ **HTTPS automatically** configured
- ✅ **No credit card** required for trial

---

## 🚀 **Step-by-Step Deployment**

### **Step 1: Prepare Your Project**

Create a `Procfile` in project root:

```bash
web: php artisan serve --host=0.0.0.0 --port=$PORT
```

Create `railway.json` (optional but recommended):

```json
{
  "$schema": "https://railway.app/railway.schema.json",
  "build": {
    "builder": "NIXPACKS",
    "buildCommand": "composer install --optimize-autoloader --no-dev && npm ci --production && npm run build && php artisan config:cache && php artisan route:cache && php artisan view:cache"
  },
  "deploy": {
    "startCommand": "php artisan serve --host=0.0.0.0 --port=$PORT",
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 10
  }
}
```

### **Step 2: Push to GitHub**

```bash
# Initialize git if not already done
git init
git add .
git commit -m "Ready for Railway deployment"

# Create GitHub repository and push
git remote add origin https://github.com/YOUR-USERNAME/traders-point.git
git branch -M main
git push -u origin main
```

### **Step 3: Deploy on Railway**

1. **Go to Railway**: https://railway.app
2. **Click "Start a New Project"**
3. **Select "Deploy from GitHub repo"**
4. **Authorize Railway** to access your GitHub
5. **Select your `traders-point` repository**
6. Railway will **auto-detect Laravel** and start deploying

### **Step 4: Add MySQL Database**

1. In Railway dashboard, click **"+ New"**
2. Select **"Database"** → **"Add MySQL"**
3. Railway will create database and add connection variables automatically

### **Step 5: Configure Environment Variables**

In Railway dashboard → **Variables** tab, add these:

```env
APP_NAME=Traders Point Academy
APP_ENV=production
APP_KEY=base64:YOUR_KEY_HERE
APP_DEBUG=false
APP_URL=https://your-railway-app.railway.app

# Database (Railway auto-fills MYSQL_URL)
# But you need to parse it or use these:
DB_CONNECTION=mysql
DB_HOST=${{MYSQLHOST}}
DB_PORT=${{MYSQLPORT}}
DB_DATABASE=${{MYSQLDATABASE}}
DB_USERNAME=${{MYSQLUSER}}
DB_PASSWORD=${{MYSQLPASSWORD}}

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# Razorpay LIVE Keys
RAZORPAY_KEY=rzp_live_XXXXXXXXXX
RAZORPAY_SECRET=YOUR_LIVE_SECRET
RAZORPAY_WEBHOOK_SECRET=whsec_XXXXXXXXXX
RAZORPAY_CURRENCY=INR

# Session
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_DOMAIN=your-railway-app.railway.app

# Cache & Queue
CACHE_STORE=file
QUEUE_CONNECTION=database

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=warning
```

### **Step 6: Run Migrations**

Railway dashboard mein:

1. **Settings** → **Deploy Triggers**
2. Add **Custom Start Command**:
   ```bash
   php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT
   ```

Or manually run via Railway CLI:
```bash
railway run php artisan migrate --force
railway run php artisan db:seed
```

### **Step 7: Configure Custom Domain (Optional)**

1. Railway dashboard → **Settings** → **Domains**
2. Click **"Generate Domain"** for free `.railway.app` subdomain
3. Or add your **custom domain** (yourdomain.com)

---

## 🎯 **Post-Deployment**

### Update Razorpay Webhook

1. Copy your Railway app URL: `https://your-app.railway.app`
2. Go to Razorpay Dashboard
3. Update webhook URL to: `https://your-app.railway.app/razorpay/webhook`

### Test Everything

- [ ] Visit your Railway URL
- [ ] Register account
- [ ] Test email delivery
- [ ] Test payment flow
- [ ] Test video streaming

---

## 🔄 **Auto-Deploy on Git Push**

Every time you push to GitHub `main` branch, Railway will automatically:
1. Pull latest code
2. Run build commands
3. Restart application

```bash
# Make changes locally
git add .
git commit -m "Fixed something"
git push origin main

# Railway auto-deploys in ~2 minutes!
```

---

## 💰 **Pricing**

**Free Tier**:
- $5 usage credit per month
- Enough for testing/small projects
- ~500 hours/month of execution

**After Free Tier**:
- ~$5-10/month for small app
- Pay only for what you use

---

## 🆘 **Troubleshooting**

### Build Fails

Check Railway logs:
1. Dashboard → **Deployments**
2. Click latest deployment
3. View **Build Logs**

Common issues:
- Missing `composer.json` → Push it to Git
- Missing `package.json` → Push it to Git
- `.env` not configured → Add variables

### App Crashes

Check **Deploy Logs**:
```
Error: APP_KEY not set
```
→ Generate key: `railway run php artisan key:generate`

### Database Connection Error

Verify these variables exist:
- `MYSQLHOST`
- `MYSQLPORT`
- `MYSQLDATABASE`
- `MYSQLUSER`
- `MYSQLPASSWORD`

Railway should auto-add them when you create MySQL.

---

## 📋 **Railway CLI (Advanced)**

Install Railway CLI:
```bash
# Windows
npm install -g @railway/cli

# Or with Scoop
scoop install railway
```

Use CLI:
```bash
# Login
railway login

# Link to project
railway link

# Run commands
railway run php artisan migrate
railway run php artisan db:seed
railway run php artisan tinker

# View logs
railway logs

# Open in browser
railway open
```

---

## ✅ **Checklist Before Going Live**

- [ ] GitHub repository is up-to-date
- [ ] Railway project deployed successfully
- [ ] MySQL database connected
- [ ] Environment variables configured
- [ ] Migrations run successfully
- [ ] APP_KEY generated
- [ ] Custom domain configured (optional)
- [ ] Razorpay webhook updated
- [ ] Test payment with ₹1
- [ ] Test email delivery
- [ ] Test video streaming

---

## 🎉 **Done!**

Your Laravel app is now live on Railway! 🚀

**Your URL**: `https://your-app.railway.app`

**Next Steps**:
- Configure custom domain
- Setup monitoring (see MONITORING.md)
- Test all features
- Share with users!

---

## 📞 **Resources**

- **Railway Docs**: https://docs.railway.app
- **Railway Discord**: https://discord.gg/railway
- **Laravel on Railway**: https://docs.railway.app/guides/laravel
