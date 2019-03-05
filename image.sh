convert \
  -background '#0008' \
  -gravity center \
  -fill white \
  -pointsize 42 \
  -size 960x960 \
  -font "arial.ttf" \
   caption:@test.txt \
   blank.jpg \
  +swap \
  -gravity south \
  -composite \
   blank2.jpg 

   #caption:@test.txt \
