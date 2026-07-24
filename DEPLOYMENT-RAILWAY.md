# Backend Deployment to Railway

## Prerequisites
- Railway account (free tier available at railway.app)
- GitHub account with access to this repository
- PostgreSQL or MySQL database (Railway provides free tier)
- Cloudflare R2 account (optional, for file storage)

## Step 1: Create Railway Account & Project

1. Go to [railway.app](https://railway.app)
2. Sign up with GitHub
3. Create a new project
4. Select "Deploy from GitHub repo"
5. Select `DongDarong/hfccf-backend`
6. Click "Deploy"

## Step 2: Add MySQL Database

In Railway Dashboard:

1. Click "Add Service" → "Database" → "MySQL"
2. Configuration:
   - Version: 8.0+
   - Leave other settings default
3. Wait for database to initialize (2-3 minutes)

## Step 3: Configure Environment Variables

In Railway Dashboard → Project → Variables, add:

```
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=${{ RAILWAY_PUBLIC_DOMAIN }}

# Database (automatically provided by MySQL plugin)
DB_CONNECTION=mysql
DB_HOST=${{ MYSQL_HOST }}
DB_PORT=${{ MYSQL_PORT }}
DB_DATABASE=${{ MYSQL_DATABASE }}
DB_USERNAME=${{ MYSQL_USER }}
DB_PASSWORD=${{ MYSQL_PASSWORD }}

# Security
APP_KEY=base64:YOUR_APP_KEY_HERE
SANCTUM_STATEFUL_DOMAINS=${{ RAILWAY_PUBLIC_DOMAIN }}

# Database
DB_FOREIGN_KEYS=true

# Mail
MAIL_DRIVER=log

# Cache & Session
CACHE_DRIVER=file
SESSION_DRIVER=cookie
QUEUE_CONNECTION=sync
```

## Step 4: Generate APP_KEY

1. Locally, run: `php artisan key:generate --show`
2. Copy the output (format: `base64:xxxxx`)
3. Paste into Railway variable `APP_KEY`

## Step 5: Deploy

1. Railway automatically deploys from GitHub
2. Watch deployment logs for errors
3. Wait for "Deployment Successful" message (5-10 minutes)

**Your backend URL**: `https://your-project-name.railway.app`

## Step 6: Run Migrations

After first deployment:

1. Click "Shell" tab in Railway
2. Run:
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=SportReportTestingSeeder
   ```

## Verify Deployment

Test the API:

```bash
curl https://your-project.railway.app/api/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Optional: Add Cloudflare R2 Storage

For file uploads (Student ID cards, reports):

1. Create Cloudflare R2 account & bucket
2. Generate API tokens
3. Add to Railway variables:
   ```
   AWS_ACCESS_KEY_ID=your_r2_key
   AWS_SECRET_ACCESS_KEY=your_r2_secret
   AWS_BUCKET=your_bucket_name
   AWS_ENDPOINT=your_r2_endpoint
   ```

## Troubleshooting

### Database Connection Issues
- Verify `DB_HOST`, `DB_PORT`, `DB_DATABASE` are correct
- Check MySQL service is running: "Deployed" status in Railway
- Migrate may fail initially - re-run after database initializes

### Build Fails
- Check PHP version: Railway uses PHP 8.3+ by default
- Verify `composer.json` has correct dependencies
- Check Procfile syntax

### Migration Issues
```bash
# In Railway Shell
php artisan migrate --force
php artisan migrate:reset --force
php artisan config:cache
php artisan route:cache
```

### Memory Issues
- Increase Railway plan or optimize queries
- Check Laravel query logs
- Use database indexing

## Post-Deployment

1. **Set up CI/CD**:
   - Railway auto-deploys on push to `main`
   - No additional configuration needed

2. **Monitor Logs**:
   - Railway Dashboard → Logs tab
   - Set up error alerts

3. **Backup Database**:
   - Enable Railway backups
   - Regular manual exports recommended

4. **Set Custom Domain** (optional):
   - Railway Dashboard → Networking
   - Add custom domain
   - Update frontend `VITE_API_BASE_URL`

## Production Checklist

- [ ] APP_KEY set correctly
- [ ] Database migrated and seeded
- [ ] MySQL service running
- [ ] Environment variables configured
- [ ] CORS configured for frontend domain
- [ ] Frontend API URL points to Railway
- [ ] Logs monitored for errors
- [ ] Backups enabled
- [ ] Custom domain configured (optional)

## Performance Tips

- Enable query caching: `CACHE_DRIVER=redis`
- Use database indexing for frequent queries
- Configure rate limiting in AppServiceProvider
- Monitor resource usage in Railway dashboard
- Consider upgrading to paid plan for production

## Support

- [Railway Documentation](https://docs.railway.app)
- [Laravel Deployment](https://laravel.com/docs/deployment)
- [Railway Support](https://railway.app/support)
