from reportlab.lib.pagesizes import letter
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_CENTER

output_path = 'chirper_changes_summary.pdf'

doc = SimpleDocTemplate(output_path, pagesize=letter, rightMargin=72, leftMargin=72, topMargin=72, bottomMargin=72)
styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name='TitleCenter', parent=styles['Title'], alignment=TA_CENTER))

content = []
content.append(Paragraph('Chirper Code Changes Summary', styles['TitleCenter']))
content.append(Spacer(1, 12))
content.append(Paragraph('This document explains the recent functionality changes made to the Chirper application, including user profile pages, search behavior, navbar updates, and supporting controllers/views.', styles['BodyText']))
content.append(Spacer(1, 18))

sections = [
    ('1. Purpose', 'Add a searchable user/profile experience so visitors can find users and chirps by keywords or user IDs, and display matching results in a dedicated search page.'),
    ('2. New and updated routes', 'Added GET /users/{user} for profile pages, GET /search for full search results, and GET /search/suggestions for AJAX-powered live suggestions. Existing chirp CRUD routes remain in place with authentication protection.'),
    ('3. Controllers', 'A new UserController displays a selected user profile and their chirps. A new SearchController performs full search results and returns JSON suggestions for the navbar dropdown. ChirpController was updated to use route model binding for edit, update, and destroy actions.'),
    ('4. Views and layout', 'Updated the main layout to include a search input in the navbar and a helper instruction message. Created users.show to display a user profile with paginated chirps. Created search.results to show matching users and chirps on the search page. The home view now uses the shared layout and shows the chirp form only to authenticated users.'),
    ('5. Search functionality', 'Search input supports user name, user ID, and chirp keyword searches. Live suggestions query the server as the user types, showing both matching users and chirp previews. Clicking a suggestion either opens a profile or preserves the search query for the search page.'),
    ('6. JavaScript', 'Added a small script in resources/js/app.js to fetch suggestions from /search/suggestions and render a dropdown beneath the navbar search input. It includes debouncing and hides suggestions when clicking outside.'),
    ('7. UI behavior', 'Chirp author names now link to that user’s profile page. On the home page, the chirp composer is shown only to signed-in users, and guests see sign in/register buttons instead. A helper message explains what can be searched.'),
    ('8. Verification', 'Routes were verified using php artisan route:list. Editor diagnostics were checked for the modified files and no syntax errors were reported.'),
]

for title, text in sections:
    content.append(Paragraph(title, styles['Heading2']))
    content.append(Spacer(1, 6))
    content.append(Paragraph(text, styles['BodyText']))
    content.append(Spacer(1, 12))

content.append(Paragraph('Modified files include:', styles['Heading2']))
content.append(Paragraph('routes/web.php, app/Http/Controllers/UserController.php, app/Http/Controllers/SearchController.php, app/Http/Controllers/ChirpController.php, resources/views/components/layout.blade.php, resources/views/home.blade.php, resources/views/users/show.blade.php, resources/views/search/results.blade.php, resources/views/components/chirp.blade.php, resources/js/app.js, .vscode/settings.json', styles['BodyText']))
content.append(Spacer(1, 18))

doc.build(content)
print('PDF written to', output_path)
