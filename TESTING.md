# Book Reviews Plugin - Testing Guide

## Manual Testing Instructions

### Setup Testing Environment

1. **Install on a Test WordPress Site:**
   - Use a local WordPress installation (Local by Flywheel, XAMPP, MAMP, etc.)
   - Or use a staging site
   - Never test on a live production site first

2. **Enable WordPress Debug Mode:**
   Add these lines to `wp-config.php` to catch any errors:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

3. **Install the Plugin:**
   - Copy the `book-reviews-plugin` folder to `wp-content/plugins/`
   - Or zip it and upload via WordPress admin

---

## Test Case 1: Plugin Activation

**Objective:** Verify the plugin activates correctly and creates necessary database structures

**Steps:**
1. Go to Plugins page in WordPress admin
2. Find "Book Reviews" plugin
3. Click "Activate"

**Expected Results:**
- ✓ Plugin activates without errors
- ✓ No PHP warnings or errors appear
- ✓ "Book Reviews" menu appears in admin sidebar with book icon
- ✓ Submenu item "Add New" is visible

**How to verify database table:**
1. Go to phpMyAdmin or use WP-CLI
2. Run query: `SHOW TABLES LIKE '%book_reviews%';`
3. Table `wp_book_reviews` (or with your prefix) should exist

---

## Test Case 2: Add a New Book (Complete)

**Objective:** Test adding a book with all fields populated

**Steps:**
1. Click "Book Reviews > Add New" in admin menu
2. Enter Title: "The Great Gatsby"
3. Enter Author: "F. Scott Fitzgerald"
4. Click on 4 stars for rating
5. Click "Upload Image" button
6. Select an image from media library (or upload new)
7. Enter review text: "A masterpiece of American literature. Beautifully written with unforgettable characters and symbolism."
8. Click "Add Book"

**Expected Results:**
- ✓ Success message appears: "Book added successfully!"
- ✓ Link to view all books is shown
- ✓ Form redirects to edit mode for the new book
- ✓ All entered data is preserved

---

## Test Case 3: Add a Book (Minimal Required Fields)

**Objective:** Test adding a book with only required fields

**Steps:**
1. Go to "Book Reviews > Add New"
2. Enter Title: "1984"
3. Enter Author: "George Orwell"
4. Click on 5 stars for rating
5. Leave image and review empty
6. Click "Add Book"

**Expected Results:**
- ✓ Book is added successfully
- ✓ No errors occur
- ✓ Empty fields are handled gracefully

---

## Test Case 4: Form Validation

**Objective:** Verify required field validation works

**Steps:**
1. Go to "Book Reviews > Add New"
2. Leave title empty
3. Enter Author: "Test Author"
4. Click 3 stars
5. Click "Add Book"

**Expected Results:**
- ✓ Form does not submit
- ✓ Browser shows "Please fill out this field" message
- ✓ No data is saved to database

**Repeat for:**
- Empty author field
- No rating selected

---

## Test Case 5: View All Books

**Objective:** Verify book list displays correctly

**Prerequisites:** Add at least 3 books with different data

**Steps:**
1. Go to "Book Reviews" main page
2. Observe the table

**Expected Results:**
- ✓ All books are displayed in a table
- ✓ Columns show: Cover, Title, Author, Rating, Review, Actions
- ✓ Cover images appear as thumbnails (if uploaded)
- ✓ Books without images show book icon placeholder
- ✓ Star ratings display correctly (filled/empty stars)
- ✓ Long reviews are truncated to ~100 characters with "..."
- ✓ Edit and Delete buttons appear for each book
- ✓ "Add New" button is visible at top

---

## Test Case 6: Edit a Book

**Objective:** Test editing existing book data

**Prerequisites:** At least one book exists

**Steps:**
1. Go to "Book Reviews"
2. Click "Edit" on any book
3. Change title to "Updated Title"
4. Change rating to different stars
5. Update review text
6. Click "Update Book"

**Expected Results:**
- ✓ Success message: "Book updated successfully!"
- ✓ Changes are saved in database
- ✓ Return to book list shows updated data

---

## Test Case 7: Change Book Cover Image

**Objective:** Test image upload and replacement

**Prerequisites:** One book with existing cover image

**Steps:**
1. Edit the book with existing image
2. Click "Change Image" button
3. Select a different image
4. Click "Update Book"

**Expected Results:**
- ✓ New image replaces old image
- ✓ Preview updates immediately
- ✓ Updated image shows in book list

---

## Test Case 8: Remove Book Cover Image

**Objective:** Test removing cover image

**Prerequisites:** One book with cover image

**Steps:**
1. Edit the book
2. Click "Remove Image" button
3. Click "Update Book"

**Expected Results:**
- ✓ Image preview disappears
- ✓ "Upload Image" button shows instead of "Change Image"
- ✓ Book list shows book icon placeholder
- ✓ Image URL is empty in database

---

## Test Case 9: Delete a Book

**Objective:** Test book deletion

**Prerequisites:** At least one book exists

**Steps:**
1. Go to "Book Reviews"
2. Click "Delete" on any book
3. Confirm deletion in browser alert

**Expected Results:**
- ✓ Confirmation dialog appears
- ✓ After confirming, book is removed from list
- ✓ Success message: "Book deleted successfully!"
- ✓ Book no longer exists in database

**Also test:**
- Click "Delete" then "Cancel" → Book should NOT be deleted

---

## Test Case 10: Rating Star Interactions

**Objective:** Test rating selector UI behavior

**Steps:**
1. Go to "Add New Book"
2. Hover over each star
3. Click on 3rd star
4. Hover over 5th star
5. Move mouse away
6. Hover over 1st star
7. Click on 5th star

**Expected Results:**
- ✓ Stars highlight yellow on hover up to hovered star
- ✓ Clicked star and all before it turn yellow
- ✓ Moving mouse away keeps selected stars yellow
- ✓ Can change selection by clicking different star
- ✓ Visual feedback is smooth and clear

---

## Test Case 11: Security - Nonce Verification

**Objective:** Verify forms are protected against CSRF

**Steps:**
1. Go to "Add New Book" page
2. Open browser DevTools > Inspector/Elements
3. Find the form
4. Delete or modify the nonce field value
5. Fill out form and submit

**Expected Results:**
- ✓ Form submission is rejected
- ✓ Error message appears or nothing happens
- ✓ No data is saved

---

## Test Case 12: Empty State

**Objective:** Test UI when no books exist

**Steps:**
1. Delete all books from the list
2. Go to "Book Reviews" main page

**Expected Results:**
- ✓ Message appears: "No books found. Add your first book!"
- ✓ Link to add book is provided
- ✓ No empty table is shown

---

## Test Case 13: Special Characters

**Objective:** Test handling of special characters in text

**Steps:**
1. Add a new book with:
   - Title: "Book with "Quotes" & <Special> Characters"
   - Author: "O'Brien & Smith"
   - Review: "Test <script>alert('xss')</script> injection"
2. Save and view in list

**Expected Results:**
- ✓ Special characters display correctly
- ✓ HTML tags are escaped (not executed)
- ✓ Quotes don't break the display
- ✓ No JavaScript execution occurs

---

## Test Case 14: Large Data Handling

**Objective:** Test with long text content

**Steps:**
1. Add a book with:
   - Very long title (250+ characters)
   - Very long review (2000+ words)
2. Save and view

**Expected Results:**
- ✓ Long title saves completely
- ✓ Long review saves completely
- ✓ List view truncates review appropriately
- ✓ Full review visible on edit page
- ✓ No database errors

---

## Test Case 15: Multiple Image Uploads

**Objective:** Test uploading multiple times

**Steps:**
1. Edit a book
2. Upload an image
3. Without saving, click "Change Image"
4. Upload different image
5. Save book

**Expected Results:**
- ✓ Latest selected image is used
- ✓ Only one image URL is saved
- ✓ No errors occur

---

## Browser Compatibility Testing

Test the admin interface in:
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile responsive view

**Check:**
- Star rating selector works
- Image upload works
- Forms submit correctly
- Table displays properly

---

## Performance Testing

**For sites with many books:**

1. Add 50+ books to the database
2. Go to "Book Reviews" list page
3. Check:
   - ✓ Page loads in < 2 seconds
   - ✓ All images load properly
   - ✓ No browser console errors
   - ✓ Smooth scrolling

---

## Error Handling Testing

**Test various error scenarios:**

1. **Database connection issues:**
   - Temporarily break database connection
   - Try to view/add books
   - Should show graceful error

2. **File upload limits:**
   - Try uploading very large image (>10MB)
   - Should show WordPress limit message

3. **Invalid data:**
   - Try rating of 0 or 6
   - Should be prevented by validation

---

## Cleanup Testing

**After all tests:**

1. Deactivate plugin
2. Reactivate plugin
3. Verify all data is still intact

4. (Optional) Uninstall test:
   - Delete plugin files
   - Check if table still exists
   - Consider if you want data preserved or deleted

---

## Bug Report Template

If you find issues during testing, document them:

```
**Bug Title:** [Brief description]
**Severity:** Critical / High / Medium / Low
**Steps to Reproduce:**
1. 
2. 
3. 

**Expected Result:**
[What should happen]

**Actual Result:**
[What actually happened]

**Environment:**
- WordPress Version:
- PHP Version:
- Browser:
- Screenshots: [if applicable]
```

---

## Test Results Summary

After completing all tests, mark each as:
- ✅ PASS
- ❌ FAIL (document in bug report)
- ⚠️ PARTIAL (works but with minor issues)

**Overall Plugin Status:** [ Ready for Production / Needs Fixes / Major Issues ]
