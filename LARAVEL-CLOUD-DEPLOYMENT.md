# Laravel Cloud Deployment Guide

This guide will help you deploy your HomyGo Laravel application to Laravel Cloud.

## Prerequisites

1. **Laravel Cloud Account**: Sign up at [laravel.cloud](https://laravel.cloud)
2. **Git Repository**: Your code should be in a Git repository (GitHub, GitLab, etc.)
3. **Laravel Cloud CLI** (optional): Install for local management

## Project Structure

Your project has been prepared with the following Laravel Cloud-specific files:

- `cloud.yaml` - Main configuration file for Laravel Cloud
- `.env.cloud` - Environment template for cloud deployment
- `deploy-cloud.ps1` - PowerShell deployment preparation script
- `deploy-cloud.sh` - Bash deployment preparation script

## Step-by-Step Deployment

### 1. Prepare Your Repository

```powershell
# Run the preparation script
.\deploy-cloud.ps1

# Or with tests
.\deploy-cloud.ps1 --with-tests
```

### 2. Commit Changes

```bash
git add .
git commit -m "Prepare for Laravel Cloud deployment"
git push origin main
```

### 3. Configure Laravel Cloud

1. **Login to Laravel Cloud Dashboard**
2. **Create New Project**
   - Connect your Git repository
   - Select the branch (main/master)
   - Laravel Cloud will detect your `cloud.yaml` file

3. **Environment Variables**
   Set these in the Laravel Cloud dashboard:

   ```env
   # Required
   APP_KEY=base64:... (generate with: php artisan key:generate --show)
   APP_NAME=HomyGo
   APP_URL=https://your-domain.com
   
   # Database (auto-configured by Laravel Cloud)
   DB_CONNECTION=mysql
   
   # Mail Configuration
   MAIL_MAILER=smtp
   MAIL_HOST=your-smtp-host
   MAIL_PORT=587
   MAIL_USERNAME=your-email
   MAIL_PASSWORD=your-password
   MAIL_FROM_ADDRESS=hello@homygo.com
   
   # Social Authentication (if using)
   FACEBOOK_CLIENT_ID=your-facebook-id
   FACEBOOK_CLIENT_SECRET=your-facebook-secret
   GOOGLE_CLIENT_ID=your-google-id
   GOOGLE_CLIENT_SECRET=your-google-secret
   
   # Stripe (if using payments)
   STRIPE_KEY=pk_live_...
   STRIPE_SECRET=sk_live_...
   STRIPE_WEBHOOK_SECRET=whsec_...
   
   # AWS S3 (optional, for file storage)
   AWS_ACCESS_KEY_ID=your-access-key
   AWS_SECRET_ACCESS_KEY=your-secret-key
   AWS_DEFAULT_REGION=us-east-1
   AWS_BUCKET=your-bucket-name
   ```

### 4. Domain Configuration

1. **Custom Domain**: Add your domain in Laravel Cloud dashboard
2. **SSL Certificate**: Laravel Cloud provides automatic SSL
3. **DNS Configuration**: Point your domain to Laravel Cloud

### 5. Database Setup

Laravel Cloud will automatically:

- Create a MySQL database
- Run migrations during deployment
- Set up database credentials

### 6. Queue and Scheduler

Your `cloud.yaml` is configured for:

- **Queue Worker**: Processes background jobs
- **Task Scheduler**: Runs Laravel's scheduled tasks

### 7. Deploy

1. **Trigger Deployment**: Push to your main branch or deploy manually from dashboard
2. **Monitor Progress**: Watch the deployment logs in Laravel Cloud dashboard
3. **Verify**: Check your application is running correctly

## Configuration Details

### cloud.yaml Breakdown

```yaml
# Your application configuration
name: homygo
type: laravel

environments:
  production:
    # Build commands run during deployment
    build:
      commands:
        - composer install --no-dev --optimize-autoloader
        - npm ci
        - npm run build
        - php artisan config:cache
        - php artisan route:cache
        - php artisan view:cache
    
    # Runtime environment
    runtime:
      php_version: "8.2"
      node_version: "20"
    
    # Database configuration
    database:
      type: mysql
      version: "8.0"
    
    # Queue workers for background jobs
    queues:
      default:
        connection: database
        processes: 1
        memory: 128
```

### Performance Optimizations

Your project includes these optimizations for Laravel Cloud:

1. **Caching**: Config, routes, and views are cached during build
2. **Autoloader Optimization**: Composer autoloader is optimized
3. **Asset Building**: Vite builds and optimizes assets
4. **Database**: Uses connection pooling and optimized queries

### Monitoring and Logs

Laravel Cloud provides:

- **Application Logs**: View via dashboard or CLI
- **Performance Metrics**: Response times, memory usage
- **Error Tracking**: Automatic error detection and alerts
- **Health Checks**: Automatic application health monitoring

## Troubleshooting

### Common Issues

1. **Build Failures**
   - Check `composer.json` for correct dependencies
   - Verify Node.js version compatibility
   - Review build logs in Laravel Cloud dashboard

2. **Database Connection Issues**
   - Ensure migrations are properly structured
   - Check for foreign key constraints
   - Verify database connection in health checks

3. **Asset Loading Issues**
   - Verify Vite configuration
   - Check public path settings
   - Ensure assets are built during deployment

4. **Queue Jobs Not Processing**
   - Check queue configuration in `cloud.yaml`
   - Verify job classes exist
   - Monitor queue worker logs

### Support Resources

- **Laravel Cloud Documentation**: [docs.laravel.cloud](https://docs.laravel.cloud)
- **Laravel Community**: [laracasts.com](https://laracasts.com)
- **GitHub Issues**: Check your repository issues
- **Laravel Cloud Support**: Available in dashboard

## Post-Deployment Checklist

- [ ] Application loads successfully
- [ ] Database migrations completed
- [ ] User registration/login works
- [ ] Email sending configured
- [ ] Social authentication (if enabled)
- [ ] Payment processing (if enabled)
- [ ] File uploads working
- [ ] Queue jobs processing
- [ ] Scheduled tasks running
- [ ] SSL certificate active
- [ ] Domain pointing correctly

## Maintenance

### Regular Tasks

1. **Monitor Performance**: Use Laravel Cloud dashboard
2. **Update Dependencies**: Keep Laravel and packages updated
3. **Backup Database**: Laravel Cloud handles automatic backups
4. **Security Updates**: Apply security patches promptly
5. **Log Monitoring**: Review application logs regularly

### Scaling

Laravel Cloud automatically handles:

- **Traffic Spikes**: Auto-scaling based on demand
- **Database Performance**: Connection pooling and optimization
- **CDN**: Global content delivery for assets
- **Caching**: Redis and application-level caching

---

Your HomyGo application is now ready for Laravel Cloud deployment! 🚀
