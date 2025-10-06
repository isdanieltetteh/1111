# Advertisement System - Testing Checklist

## Pre-Testing Setup

- [ ] Database has 24 ad spaces configured (check `ad_spaces` table)
- [ ] Base pricing configured in `ad_pricing` table
- [ ] Test user has credits in account
- [ ] Admin user account available

---

## 1. User Ad Purchase Flow

### Text Ad Purchase
- [ ] Navigate to `/buy-ads.php`
- [ ] Credit balance displays correctly at top
- [ ] Select "Text Ad" option
- [ ] Choose duration (e.g., 7 days)
- [ ] Pricing updates correctly
- [ ] Select "Standard" visibility
- [ ] Choose ad space from dropdown
- [ ] All enabled spaces appear in dropdown
- [ ] Space shows dimensions and multiplier info
- [ ] Enter ad title (internal name)
- [ ] Enter target URL
- [ ] Enter headline in "Ad Headline" field
- [ ] **VERIFY: Live preview shows headline immediately**
- [ ] Type in "Ad Description" field
- [ ] **VERIFY: Live preview updates description in real-time**
- [ ] Cost summary displays correctly
- [ ] Base price shows
- [ ] Premium cost hidden (standard selected)
- [ ] Total cost calculated correctly
- [ ] Click "Purchase Advertisement"
- [ ] **VERIFY: Success message appears**
- [ ] **VERIFY: Redirected to my-ads.php**
- [ ] **VERIFY: Credits deducted from balance**
- [ ] **VERIFY: Ad shows in my ads with "pending" status**

### Banner Ad Purchase
- [ ] Navigate to `/buy-ads.php`
- [ ] Select "Banner Ad" option
- [ ] Choose duration (e.g., 3 days)
- [ ] Select "Premium" visibility
- [ ] Choose ad space
- [ ] **VERIFY: Recommended size updates based on space**
- [ ] Enter ad title
- [ ] Enter target URL
- [ ] Click "Choose File" for banner image
- [ ] Select an image file (JPG/PNG/GIF)
- [ ] **VERIFY: Image preview appears below upload field**
- [ ] **VERIFY: Preview shows actual uploaded image**
- [ ] Enter alt text
- [ ] Check cost summary
- [ ] **VERIFY: Premium cost row appears**
- [ ] **VERIFY: Total includes premium multiplier**
- [ ] Purchase ad
- [ ] **VERIFY: Success and proper credits deduction**

### Space-Based Pricing
- [ ] Select space with 2.0x multiplier (e.g., Homepage Top)
- [ ] **VERIFY: Price doubles from base**
- [ ] Select space with 1.0x multiplier
- [ ] **VERIFY: Price matches base**
- [ ] Switch between different spaces
- [ ] **VERIFY: Price updates automatically**

---

## 2. Admin Control Panel

### Access and Overview
- [ ] Log in as admin
- [ ] Navigate to `/admin/ad-control.php`
- [ ] **VERIFY: Dashboard statistics display**
  - [ ] Total spaces count
  - [ ] Enabled spaces count
  - [ ] Active ads count
  - [ ] Total impressions
  - [ ] Total clicks
  - [ ] Total revenue
- [ ] **VERIFY: All 24 spaces listed in table**
- [ ] **VERIFY: Each space shows metrics**

### Enable/Disable Functionality
- [ ] Find any enabled space in table
- [ ] Click "Disable" button
- [ ] **VERIFY: Success message appears**
- [ ] **VERIFY: Badge changes to red "Disabled"**
- [ ] **VERIFY: Button now says "Enable"**
- [ ] Navigate to `/buy-ads.php`
- [ ] **VERIFY: Disabled space NOT in dropdown**
- [ ] Return to admin panel
- [ ] Click "Enable" button on same space
- [ ] **VERIFY: Space re-enabled successfully**
- [ ] **VERIFY: Space appears in buy-ads dropdown again**

### Update Pricing
- [ ] Click "Pricing" button on any space
- [ ] **VERIFY: Modal opens with current values**
- [ ] Change multiplier (e.g., from 1.5 to 2.0)
- [ ] Click "Update Pricing"
- [ ] **VERIFY: Success message appears**
- [ ] **VERIFY: Table updates with new multiplier**
- [ ] Navigate to `/buy-ads.php`
- [ ] Select that space
- [ ] **VERIFY: Price reflects new multiplier**

### Update Dimensions
- [ ] Click "Size" button on any space
- [ ] **VERIFY: Modal shows current dimensions**
- [ ] Change width (e.g., 728 to 970)
- [ ] Change height (e.g., 90 to 250)
- [ ] Click "Update Dimensions"
- [ ] **VERIFY: Success message**
- [ ] **VERIFY: Table shows new dimensions**
- [ ] Navigate to `/buy-ads.php`
- [ ] Select banner ad type
- [ ] Select that space
- [ ] **VERIFY: Recommended size shows new dimensions**

### Performance Metrics
- [ ] Check "Active Ads" column for spaces with ads
- [ ] **VERIFY: Count is accurate**
- [ ] Check "Impressions" column
- [ ] **VERIFY: Numbers are reasonable**
- [ ] Check "Clicks" column
- [ ] Check "CTR" column
- [ ] **VERIFY: CTR = (Clicks / Impressions) × 100**
- [ ] Check "Revenue" column
- [ ] **VERIFY: Sum of all ad costs for that space**

---

## 3. Ad Display System

### Placeholder Display
- [ ] Choose a page (e.g., `index.php`)
- [ ] Add code: `<?php displayAdSpace('index_top_banner'); ?>`
- [ ] Ensure NO active ads exist for this space
- [ ] Load the page
- [ ] **VERIFY: Placeholder appears**
- [ ] **VERIFY: Shows space name**
- [ ] **VERIFY: Shows dimensions (if configured)**
- [ ] **VERIFY: "Advertise Here" button visible**
- [ ] Click "Advertise Here" button
- [ ] **VERIFY: Redirects to `/buy-ads.php`**
- [ ] **VERIFY: Space is pre-selected in dropdown (if URL param supported)**

### Active Ad Display
- [ ] Admin approves a text ad
- [ ] Set status = 'active'
- [ ] Set start_date = NOW()
- [ ] Set end_date = NOW() + interval
- [ ] Visit page with matching ad space
- [ ] **VERIFY: Ad displays (not placeholder)**
- [ ] **VERIFY: Headline shows correctly**
- [ ] **VERIFY: Description shows correctly**
- [ ] **VERIFY: "Sponsored" label appears**

### Banner Ad Display
- [ ] Admin approves a banner ad
- [ ] Visit page with matching ad space
- [ ] **VERIFY: Banner image displays**
- [ ] **VERIFY: Image is properly sized**
- [ ] **VERIFY: Alt text set correctly**
- [ ] **VERIFY: "Advertisement" label appears**

---

## 4. Impression & Click Tracking

### Impression Tracking
- [ ] Note current impression count for an ad
- [ ] Visit page displaying that ad
- [ ] Refresh page 3 times
- [ ] Check `user_advertisements` table
- [ ] **VERIFY: impression_count increased by 3**
- [ ] Check `ad_impressions` table
- [ ] **VERIFY: 3 new records added**
- [ ] **VERIFY: Records have IP, user_agent, page_url**

### Click Tracking
- [ ] Note current click count for an ad
- [ ] Visit page displaying that ad
- [ ] Click the ad
- [ ] **VERIFY: Redirected to target URL**
- [ ] Check `user_advertisements` table
- [ ] **VERIFY: click_count increased by 1**
- [ ] Check `ad_clicks` table
- [ ] **VERIFY: New record added**
- [ ] **VERIFY: Record has IP, user_agent, referrer_url**

---

## 5. Ad Rotation & Priority

### Premium vs Standard Rotation
- [ ] Create 2 ads for same space:
  - Ad A: Standard visibility
  - Ad B: Premium visibility
- [ ] Approve both ads
- [ ] Refresh page displaying that space 20 times
- [ ] **VERIFY: Premium ad (B) appears more often**
- [ ] **VERIFY: Both ads appear at least once**

### Fair Rotation Within Priority
- [ ] Create 3 standard ads for same space
- [ ] Approve all 3
- [ ] Refresh page 30 times, tracking which ad shows
- [ ] **VERIFY: All 3 ads appear**
- [ ] **VERIFY: Distribution is roughly equal**

---

## 6. Edge Cases

### No Credits
- [ ] Set user credit balance to 0
- [ ] Try to purchase ad
- [ ] **VERIFY: Error message about insufficient credits**
- [ ] **VERIFY: No ad created**
- [ ] **VERIFY: Credits not deducted**

### Invalid Image Upload
- [ ] Select banner ad type
- [ ] Try to upload a text file (.txt)
- [ ] **VERIFY: Error about invalid format**
- [ ] Try to upload huge image (>2MB)
- [ ] **VERIFY: Error about file size**

### Expired Ads
- [ ] Create ad with end_date in the past
- [ ] Set status = 'active'
- [ ] Visit page with that ad space
- [ ] **VERIFY: Placeholder shows (not expired ad)**
- [ ] Run cron: `/cron/expire_ads_cron.php`
- [ ] **VERIFY: Ad status changes to 'expired'**

### Disabled Space
- [ ] Admin disables a space
- [ ] Active ads still exist for that space
- [ ] Visit page calling displayAdSpace() for that space
- [ ] **VERIFY: Nothing displays (no placeholder, no ad)**

---

## 7. Integration Examples

### Example Page
- [ ] Visit `/ad-placement-example.php`
- [ ] **VERIFY: Page loads without errors**
- [ ] **VERIFY: All code snippets display correctly**
- [ ] **VERIFY: Live examples show (placeholders or ads)**
- [ ] **VERIFY: Table of all spaces displays**
- [ ] **VERIFY: Each space shows correct status**

---

## 8. Documentation

### README Files
- [ ] Open `/AD_SYSTEM_README.md`
- [ ] **VERIFY: All sections complete and accurate**
- [ ] Open `/QUICK_START.md`
- [ ] **VERIFY: Examples are copy-pasteable**
- [ ] Open `/CHANGES_SUMMARY.md`
- [ ] **VERIFY: All changes documented**
- [ ] Open `/SYSTEM_FLOW.txt`
- [ ] **VERIFY: Diagram is clear and helpful**

---

## 9. Security Testing

### SQL Injection
- [ ] Try entering `' OR 1=1 --` in form fields
- [ ] **VERIFY: No SQL errors**
- [ ] **VERIFY: Input treated as string**

### XSS
- [ ] Enter `<script>alert('XSS')</script>` in text ad headline
- [ ] Save and display ad
- [ ] **VERIFY: Script tags shown as text, not executed**

### File Upload
- [ ] Rename a .php file to .jpg
- [ ] Try to upload as banner
- [ ] **VERIFY: Rejected (MIME type check)**

### Authentication
- [ ] Log out
- [ ] Try to access `/buy-ads.php`
- [ ] **VERIFY: Redirected to login**
- [ ] Try to access `/admin/ad-control.php` as non-admin
- [ ] **VERIFY: Access denied**

---

## 10. Performance

### Page Load Times
- [ ] Load page with 5 ad spaces
- [ ] **VERIFY: Page loads in <2 seconds**
- [ ] **VERIFY: No N+1 query issues**

### Database Queries
- [ ] Enable query logging
- [ ] Load page with multiple ads
- [ ] **VERIFY: Efficient queries (indexed lookups)**

---

## Final Checklist

- [ ] All user-facing features work correctly
- [ ] Admin panel fully functional
- [ ] Live previews working (text & banner)
- [ ] Pricing calculations accurate
- [ ] Ad display and rotation working
- [ ] Tracking (impressions & clicks) working
- [ ] Placeholders appear when expected
- [ ] Security measures in place
- [ ] Documentation complete
- [ ] No PHP errors or warnings
- [ ] No JavaScript console errors
- [ ] Responsive design works on mobile

---

## Post-Testing Actions

After completing all tests:

1. [ ] Document any bugs found
2. [ ] Fix critical issues
3. [ ] Deploy to production
4. [ ] Monitor error logs for 24 hours
5. [ ] Check performance metrics
6. [ ] Gather user feedback

---

## Support Contacts

If issues found:
- Check `/AD_SYSTEM_README.md` for troubleshooting
- Review `/SYSTEM_FLOW.txt` for logic flow
- Examine database schema in `/schema.sql`
- Contact system administrator

**Testing Date**: _______________
**Tested By**: _______________
**Result**: ☐ PASS  ☐ FAIL  ☐ PARTIAL

**Notes**:
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
